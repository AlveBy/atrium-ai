# Atrium AI

AI writing help for the [Atrium](https://github.com/AlveBy/atrium) admin, on top of the official [Laravel AI SDK](https://laravel.com/docs/13.x/ai-sdk) (`laravel/ai`). A **Generate** link under a text field writes:

- a description from the record's name and other fields;
- a short preview text from the long one;
- the SEO title, description and keywords from the text;
- the alt text of a picture (the model looks at the image);
- a translation of the field into the site's other content languages (**Translate**).

The text comes up in a dialog. The editor reads it, edits it and either puts it into the field or cancels. The AI never saves anything: the record changes only when the editor presses **Save**.

Any provider the SDK supports works. The defaults are free tiers: Google Gemini, Groq, OpenRouter's free models, or Ollama on your own server. A paid provider is a line in `.env`.

> **Privacy.** Gemini's free tier lets Google use your prompts and answers to improve its products, and people may read them. The prompt holds the record's text. For anything private use Ollama (nothing leaves your server) or a paid key, whose terms do not allow that.

## Install

```bash
composer require alveby/atrium-ai
php artisan atrium:module:install alveby/atrium-ai
```

The second command creates the usage log table and lists the module in `config/atrium.php` → `modules`.

Then give it at least one provider key in `.env`. The SDK reads them from there; this package never stores a key:

```dotenv
GEMINI_API_KEY=...        # https://aistudio.google.com/apikey, free
GROQ_API_KEY=...          # https://console.groq.com/keys, free
OPENROUTER_API_KEY=...    # https://openrouter.ai/keys, free models end in :free
```

No key, no buttons: until a provider in the chain has a key, the admin looks as it did. Check what the module sees:

```bash
php artisan atrium:ai:status
```

## Which fields get the buttons

A field opts in with `->ai()` on `TextField`, `TextareaField` and `ContentField`:

```php
TextField::make('name')->label('Name'),
ContentField::make('detail_text')->label('Description')->ai(),
ContentField::make('preview_text')->label('Preview')->ai('preview', from: 'detail_text'),
TextField::make('picture_alt')->label('Picture alt')->ai('alt', from: 'picture'),
```

| Task | What it writes | Works from |
|---|---|---|
| `describe` (default) | The field's text | Every other text field of the form, or the fields in `from` |
| `preview` | One or two sentences, up to 300 characters | `from`, or the form |
| `seo_title` | Up to 60 characters | `from`, or the form |
| `seo_description` | Up to 160 characters | `from`, or the form |
| `seo_keywords` | Comma-separated keywords | `from`, or the form |
| `alt` | Up to 125 characters | The image field in `from` |

The SEO fields of `HasAdminSeo` (title, description, keywords, picture alt) have it already. A field with `maxLength()` gets no more than that. A `ContentField` gets simple HTML, other fields plain text. When the fields in `from` are empty, the rest of the form is used.

The fields are read as they are in the form now, not as saved, so a new record works too. Unless a field names them in `from`, codes, e-mails, phone numbers, web addresses and passwords never go into a prompt. The field and its task come from the model, never from the browser.

**Translate** shows on a translatable field when the site has more than one content language. It translates the language being edited into every other one and shows them all in the dialog. A language cleared there keeps its old text.

The answer is in the language being edited, whatever the language of the source. The model is told that language in English, with the script where it could guess wrong: "Uzbek in Latin script (Oʻzbekcha, uz)", "Kazakh in Cyrillic script (Қазақша, kk)". All fifteen Atrium languages are named that way; any other keeps the name it has under **Languages**.

## Providers and the fallback chain

`config/atrium-ai.php` (`php artisan vendor:publish --tag=atrium-ai-config`) names the providers for each kind of work, in order:

```php
'chains' => [
    'text' => env('ATRIUM_AI_TEXT', 'gemini,groq,openrouter:meta-llama/llama-3.3-70b-instruct:free'),
    'vision' => env('ATRIUM_AI_VISION', 'gemini,openrouter:google/gemma-3-27b-it:free'),
    'embeddings' => env('ATRIUM_AI_EMBEDDINGS', 'gemini'),
],
```

`provider` or `provider:model`. Without a model the SDK's default for that provider is used. A provider without a key is skipped. A provider may be listed more than once with different models (`openrouter:model-a:free,openrouter:model-b:free`): they are tried one after the other. When an entry answers with a rate limit, an overload or a timeout, the next entry is asked. So it is when a provider refuses the region it is reached from (a 403, or Gemini's 400 "User location is not supported"): the next entry is asked, and that provider is left out of every chain for an hour (`refused_for` in `config/atrium-ai.php`), so that each request does not wait for the refusal again. Every other error, a wrong key or a model that does not exist, is shown to the editor. The `alt` button needs a provider in `vision`.

Ollama needs no key. List it and set its address:

```dotenv
ATRIUM_AI_TEXT=ollama:llama3.2
ATRIUM_AI_VISION=ollama:gemma3:4b
OLLAMA_URL=http://127.0.0.1:11434
```

A paid provider is the same: `OPENAI_API_KEY` or `ANTHROPIC_API_KEY` in `.env`, and `ATRIUM_AI_TEXT=openai,gemini`. Every provider and its key name is in the SDK's `config/ai.php`.

| Setting | Default | What it does |
|---|---|---|
| `ATRIUM_AI_ENABLED` | `true` | The buttons, without removing the keys |
| `ATRIUM_AI_TEXT`, `ATRIUM_AI_VISION`, `ATRIUM_AI_EMBEDDINGS` | see above | The chains |
| `ATRIUM_AI_TIMEOUT` | `60` | Seconds one provider may take before the next one is asked |
| `refused_for` (config only) | `3600` | Seconds a provider that refused the region is left out |
| `ATRIUM_AI_QUEUE_CONNECTION`, `ATRIUM_AI_QUEUE` | none | A queue for the generation job; none = right after the response, no worker |
| `ATRIUM_AI_LOG` | `true` | The usage log |

## The form does not wait

**Generate** answers at once, and the dialog asks for the result every second and a half. By default the provider is called in the same PHP process right after that answer has gone out, so no queue worker is needed. On a busy site, give it a queue and a worker instead:

```dotenv
ATRIUM_AI_QUEUE_CONNECTION=database
ATRIUM_AI_QUEUE=ai
```

```bash
php artisan queue:work --queue=ai,default
```

The result waits ten minutes (`result_ttl`), and only the admin who asked can read it.

## Your own prompts

Each task's prompt is a Blade view. Publish them and edit:

```bash
php artisan vendor:publish --tag=atrium-ai-prompts
```

They land in `resources/views/vendor/atrium-ai/prompts`. Or point a task at a view of your own in `config/atrium-ai.php` → `prompts`. A view gets `$field` (the field's label), `$sources` (label → text), `$text` (all of them as one text), `$language` ("English (en)", "Uzbek in Latin script (Oʻzbekcha, uz)"), `$html`, `$limit` and, for `translate`, `$target`. `system` is the instruction every task starts with.

## The helper

The Atrium arch in the bottom right corner of every admin page opens a chat. The administrator asks in their own language ("how do I add a menu item?", "how many products are out of stock?") and the helper answers from the guides and from the records, with links into the admin. It only reads; it changes nothing.

It has tools, and the model decides which to call:

| Tool | What it does |
|---|---|
| `list_modules` | The modules this administrator may open, with their admin addresses |
| `describe_module` | A module's fields, filters and sorts, as the API documents them |
| `list_records` | Records of a module: search, filters, sort, up to 10 at a time |
| `get_record` | One record by its id |
| `search_docs` | Sections of the guides (`docs/`) and the module READMEs that match the words; a guide translated into the administrator's language is read instead of the English one |

The answer is in the language of the question, whatever the language of the panel; the panel's language is used only when the question's cannot be told (a single word, a number). Other modules add tools of their own: with `alveby/atrium-monitoring` installed there is `site_speed`, the numbers of its screen (the response time, the share of failed requests, the slowest pages) for an administrator who may open it.

The record tools read through the API's own controller with the permissions of the administrator who asked: a module they may not view does not exist for the helper. No token is created.

The answer is written after the response, like a generation, and the chat asks for it every second and a half. The conversation stays in the browser tab (the last 20 messages); the last 10 go with each question.

> **Privacy.** The question, the guide sections and the records the helper reads go to the provider of the `text` chain. With Gemini's free tier that means Google may read them. On a site with private data use Ollama or a paid key, or turn the helper off.

```dotenv
ATRIUM_AI_HELP=false  # no arch, no chat
```

In `config/atrium-ai.php`, `help.docs` lists the Markdown to search (a folder means every `.md` in it; globs work), and `help.tools` adds tools of your own: classes implementing `Laravel\Ai\Contracts\Tool`, made by the container, which passes `$user` (the `AdminUser`) and `$locale` to a constructor that asks for them. The prompt is the `help` view.

## API and MCP

The same generation is open to programs, with the API's tokens and rules (the `{module}:write` ability, and the owner's right to edit the module):

```bash
curl -X POST https://example.com/api/v1/ai/generate \
  -H "Authorization: Bearer <token>" -H "Content-Type: application/json" \
  -d '{"module": "products", "field": "preview_text", "id": 7}'
```

With `id` the saved record is used; `values` overrides fields; `locale` and `translate` work as in the admin. The answer is `{"data": {"value": "…", "provider": "gemini", "model": "…"}}`, or `{"data": {"values": {"de": "…"}}}` for a translation. Nothing is saved.

An AI assistant connected to `/mcp` gets the same as the `generate_text` tool.

## Public demo

On a public demo (`ATRIUM_DEMO=true`, see [docs/demo.md](../../docs/demo.md)) every visitor is the same administrator, so the per-minute throttle is shared by all of them and cannot stop one visitor from spending the provider's whole free quota for the day. While the demo mode is on, the module therefore:

- **Caps the day.** The site as a whole gets `ATRIUM_AI_DEMO_DAILY_LIMIT` provider calls a day (40 by default, below the 50 requests a day that an OpenRouter key with no credit gets for all its free models; `0` for no cap), counted from midnight in the app's time zone. A text, a helper question and the **Generate** of every field each take one call; a translation takes one per language. The panel's button, the helper, `POST /api/v1/ai/generate` and the `generate_text` tool of MCP share the one allowance. A call that fails at the provider still counts, since the provider counted it too; a request refused for its fields does not.
- **Refuses, without asking a provider,** once the allowance is gone: the dialog and the chat say "The demo's daily AI limit is used up. Try again tomorrow." (in the admin's language), and the API and MCP answer `429` with the same message and a `Retry-After` header holding the seconds until midnight. A translation that would not fit into what is left is refused whole and takes nothing.
- **Says the demo is public.** The dialog under **Generate** and the helper's chat carry a line that personal data must not be typed in.

The count is kept in a cache store of its own, `ATRIUM_AI_DEMO_STORE` (`file` by default), and not in the `ai_usage` table: the demo's reset empties the database every hour, the usage log and a `database` cache included, and the visitors would get a fresh allowance each hour. Use any store that survives the reset (`file`, `redis`, `memcached`). Outside demo mode nothing is counted and nothing is limited.

## Usage log

Every call is a row in `ai_usage`: task, module, admin, provider, model, input and output tokens, duration, and whether it succeeded. Never the texts. `php artisan atrium:ai:status --days=7` adds them up per provider.

## Tests

```bash
vendor/bin/phpunit
```

The tests use the SDK's fakes and make no network calls.

## License

MIT
