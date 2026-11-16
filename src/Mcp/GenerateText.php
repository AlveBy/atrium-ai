<?php

declare(strict_types=1);

namespace Atrium\Ai\Mcp;

use Atrium\Admin\Mcp\ApiGateway;
use Atrium\Admin\Mcp\Tools\ApiTool;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;

#[Name('generate_text')]
#[Description('Have the site\'s own AI write a field the way the admin\'s "Generate" button does: a description, a preview text, SEO title/description/keywords, an image alt text, or a translation into the site\'s other languages. Nothing is saved: pass the text to update_record when it is good. Only fields that describe_module marks with "ai" can be generated.')]
class GenerateText extends ApiTool
{
    public function schema(JsonSchema $schema): array
    {
        return [
            ...$this->moduleArgument($schema),
            'field' => $schema->string()->description('The field to write, e.g. "preview_text".')->required(),
            'id' => $schema->integer()->description('Record id: its saved values are the source.'),
            'values' => $schema->object()->description('Field values to use instead of (or without) the saved ones, e.g. {"name": "…"}.'),
            'locale' => $schema->string()->description('Language code to write in; the site\'s default language when omitted.'),
            'translate' => $schema->boolean()->description('Translate the field from "locale" into the site\'s other languages instead.'),
        ];
    }

    public function handle(Request $request, ApiGateway $api): Response
    {
        $module = $this->module($request);

        if ($module === null) {
            return $this->unknownModule($request);
        }

        return $api->call('POST', 'ai/generate', $this->siteQuery($request), array_filter([
            'module' => $module,
            'field' => (string) $request->get('field', ''),
            'id' => $request->get('id'),
            'values' => $request->get('values'),
            'locale' => $request->get('locale'),
            'translate' => $request->get('translate'),
        ], static fn ($value): bool => $value !== null));
    }
}
