<?php

/*
 * This file is part of Atrium (https://github.com/AlveBy/atrium).
 * Copyright (c) 2026 AlveBy. Licensed under the MIT License, see LICENSE.
 */

declare(strict_types=1);

namespace Atrium\Ai;

use Atrium\Admin\Contracts\Administrable;
use Atrium\Admin\Fields\ContentField;
use Atrium\Admin\Fields\Field;
use Atrium\Admin\Fields\TextareaField;
use Atrium\Admin\Fields\TextField;
use Atrium\Admin\Fields\WysiwygField;
use Atrium\Admin\Support\ContentLanguages;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * Builds a brief from a module's form: which field asked, what its ->ai()
 * says, and the values typed into the form (saved or not). The field and its
 * task come from the model, never from the browser.
 */
final class Briefs
{
    public function __construct(
        private readonly ContentLanguages $languages,
        private readonly Images $images,
    ) {}

    /**
     * @param  class-string<Model>  $model
     * @param  array<string,mixed>  $values  field name => value as the form holds it
     * @param  bool  $improve  correct the text that is in the field instead of writing one
     * @param  string|null  $selection  with $improve: the piece of the text picked out in the editor
     *
     * @throws ValidationException when the field has no AI or there is nothing to work from
     */
    public function make(string $model, string $name, array $values, ?string $locale = null, bool $translate = false, ?string $module = null, ?int $user = null, bool $improve = false, ?string $selection = null): Brief
    {
        if ($improve && $translate) {
            throw ValidationException::withMessages(['improve' => __('validation.prohibits', ['attribute' => 'improve', 'other' => 'translate'])]);
        }

        $fields = $this->fields($model);
        $field = $fields[$name] ?? null;
        $ai = $field !== null && method_exists($field, 'getAi') ? $field->getAi() : null;

        if ($field === null || $ai === null) {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.no_ai')]);
        }

        $language = $locale !== null && $this->languages->has($locale) ? $locale : $this->languages->default();
        $task = $translate ? Task::Translate : (Task::tryFrom($ai['task']) ?? Task::Describe);
        $format = $field instanceof ContentField ? 'html' : 'text';
        $max = $this->maxLength($field);
        $common = ['field' => $field->getLabel() ?: $name, 'format' => $format, 'max' => $max, 'module' => $module, 'user' => $user];

        if ($improve) {
            return $this->improvement($field, $task, $values, $language, $common, $selection);
        }

        if ($task === Task::Translate) {
            return $this->translation($model, $field, $values, $language, $common);
        }

        if ($task === Task::Alt) {
            $image = $this->images->local($this->text($values[$ai['from'][0] ?? ''] ?? null, $language, false));

            if ($image === null) {
                throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.no_image')]);
            }

            // The record's name helps to say what is in the picture.
            $sources = $this->sources($fields, ['name', 'title'], $values, $language, $name);

            return new Brief($task, $language, $sources, ...$common, image: $image);
        }

        // The named sources first; when they are empty (a preview before the
        // detail text is written), whatever else the form holds.
        $sources = $this->sources($fields, $ai['from'], $values, $language, $name)
            ?: $this->sources($fields, [], $values, $language, $name);

        if ($sources === []) {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.no_source')]);
        }

        return new Brief($task, $language, $sources, ...$common);
    }

    /**
     * The text of the field as it stands (or the piece picked out of it), to be
     * corrected. The result goes back where the text came from, so a text too
     * long to be sent whole is refused instead of cut.
     *
     * @param  array<string,mixed>  $values
     * @param  array<string,mixed>  $common
     */
    private function improvement(Field $field, Task $task, array $values, string $language, array $common, ?string $selection): Brief
    {
        $piece = $selection !== null && trim($selection) !== '' ? $selection : null;
        $fragment = $piece !== null;
        $raw = $piece ?? $this->raw($values[$field->getName()] ?? null, $language, strict: true);
        $text = $this->clean($raw, $common['format'] === 'html');

        // Tags alone (an empty paragraph) are no text.
        if ($this->clean($raw, false) === '') {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.no_source')]);
        }

        if (mb_strlen(trim($raw)) > $this->sourceLength()) {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.too_long')]);
        }

        // The whole text keeps the limits of the field; a piece has none of its own.
        $limits = array_filter([$common['max'], $task->limit()], static fn (?int $limit): bool => $limit !== null);
        $common['max'] = $fragment || $limits === [] ? null : min($limits);

        return new Brief(Task::Improve, $language, [$common['field'] => $text], ...$common, fragment: $fragment);
    }

    /**
     * @param  array<string,mixed>  $values
     * @param  array<string,mixed>  $common
     */
    private function translation(string $model, Field $field, array $values, string $language, array $common): Brief
    {
        $instance = new $model;

        if (! method_exists($instance, 'isTranslatableAttribute') || ! $instance->isTranslatableAttribute($field->getName())) {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.not_translatable')]);
        }

        $source = $this->text($values[$field->getName()] ?? null, $language, $common['format'] === 'html', strict: true);
        $targets = $this->languages->all()
            ->reject(fn ($item): bool => $item->code === $language)
            ->mapWithKeys(fn ($item): array => [$item->code => LanguageNames::describe($item->code, $item->name)])
            ->all();

        if ($source === '' || $targets === []) {
            throw ValidationException::withMessages(['field' => __('atrium-ai::ai.errors.no_source')]);
        }

        return new Brief(Task::Translate, $language, [$common['field'] => $source], ...$common, targets: $targets);
    }

    /**
     * @param  array<string,Field>  $fields
     * @param  array<int,string>  $from  empty = every text field of the form
     * @param  array<string,mixed>  $values
     * @return array<string,string>
     */
    private function sources(array $fields, array $from, array $values, string $language, string $self): array
    {
        $names = $from !== [] ? $from : array_keys(array_filter($fields, fn (Field $field): bool => $this->isText($field)));
        $sources = [];

        foreach ($names as $name) {
            if ($name === $self || ! isset($fields[$name])) {
                continue;
            }

            $text = $this->text($values[$name] ?? null, $language, false);

            if ($text !== '') {
                $sources[$fields[$name]->getLabel() ?: $name] = $text;
            }
        }

        return $sources;
    }

    private function isText(Field $field): bool
    {
        if ($field instanceof TextField) {
            return ! in_array($field->toArray()['inputType'] ?? 'text', ['password', 'email', 'tel', 'url'], true);
        }

        return $field instanceof TextareaField || $field instanceof ContentField || $field instanceof WysiwygField;
    }

    /**
     * The text of a value in a language: a translatable value is
     * {"en": "…", "de": "…"}; without the language the default one stands in,
     * unless $strict (the text to translate must be the one on screen).
     */
    private function text(mixed $value, string $language, bool $keepHtml, bool $strict = false): string
    {
        return $this->clean($this->raw($value, $language, $strict), $keepHtml);
    }

    /**
     * A string ready for a prompt: plain text unless $keepHtml, trimmed and cut.
     */
    private function clean(string $text, bool $keepHtml): string
    {
        if (! $keepHtml) {
            $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", $text)), ENT_QUOTES | ENT_HTML5);
            $text = (string) preg_replace(['/[ \t]+/u', '/\n\s*\n+/u'], [' ', "\n\n"], $text);
        }

        return mb_substr(trim($text), 0, $this->sourceLength());
    }

    /**
     * The value as one string, as the form holds it: no tags removed, nothing cut.
     */
    private function raw(mixed $value, string $language, bool $strict): string
    {
        if (is_array($value)) {
            $value = $value[$language] ?? ($strict ? null : ($value[$this->languages->default()] ?? collect($value)->first(fn ($item): bool => is_string($item) && trim($item) !== '')));
        }

        return is_string($value) || is_numeric($value) ? (string) $value : '';
    }

    private function sourceLength(): int
    {
        return max(100, (int) config('atrium-ai.max_source_length', 6000));
    }

    private function maxLength(Field $field): ?int
    {
        $max = $field->toArray()['maxLength'] ?? null;

        return is_int($max) && $max > 0 ? $max : null;
    }

    /**
     * @param  class-string<Model>  $model
     * @return array<string,Field>
     */
    private function fields(string $model): array
    {
        if (! is_a($model, Administrable::class, true)) {
            return [];
        }

        $fields = method_exists($model, 'getAdminFieldsWithTabs')
            ? $model::getAdminFieldsWithTabs()
            : $model::getAdministrationFields();
        $byName = [];

        foreach ($fields as $field) {
            if ($field instanceof Field) {
                $byName[$field->getName()] = $field;
            }
        }

        return $byName;
    }
}
