# Form authoring

Create a polished respondent-facing form, not merely a valid JSON structure.

## Visible copy and technical identifiers

- `name` is always visible respondent-facing copy. Write natural labels in sentence case with spaces: `Full name`, `Email address`, `Phone number`.
- Never put `full_name`, `contact_email`, kebab-case, database keys, variable names, or internal terminology in `name`.
- `id` is the technical identifier. Omit it for new blocks and let OpnForm generate it. Preserve existing IDs when revising a draft.
- Keep labels, placeholders, help text, options, buttons, and completion copy in the user's language.

Correct:

```json
{
  "name": "Email address",
  "type": "email",
  "placeholder": "you@example.com",
  "required": true
}
```

Incorrect:

```json
{
  "name": "contact_email",
  "type": "email"
}
```

## Computed variables and display logic

- A computed variable needs a unique `id` beginning with `cv_`, a unique human-readable `name`, and a non-empty `formula`. Reference block and variable IDs with braces, for example `{budget} * 1.2`.
- Attach `logic` to the block whose behavior should change. A condition must reference another block or variable; it cannot reference its own target block.
- Keep `identifier` equal to `value.property_meta.id`. Use the referenced block type in `property_meta.type`, or `computed` for a computed variable.
- Read `operators_by_reference_type` from `opnform://reference/form-fields/v1` instead of guessing an operator. Use only actions listed by the schema and let `validate_form_definition` check whether each action is compatible with the target block.
- To revise a draft, replace the complete `computed_variables` list through `set_form_values`, and add, replace, or clear a block's `logic` through `add_block` or `update_block`.

Example:

```json
{
  "computed_variables": [
    {
      "id": "cv_priority_score",
      "name": "Priority score",
      "formula": "{budget} * 1.2",
      "result_type": "number"
    }
  ],
  "logic": {
    "conditions": {
      "operatorIdentifier": "and",
      "children": [
        {
          "identifier": "cv_priority_score",
          "value": {
            "operator": "greater_than",
            "property_meta": {
              "id": "cv_priority_score",
              "type": "computed"
            },
            "value": 10000
          }
        }
      ]
    },
    "actions": ["show-block"]
  }
}
```

## `nf-text` uses HTML

The `content` property of an `nf-text` block is a sanitized HTML fragment. Never use Markdown syntax such as `# Heading`, `**bold**`, Markdown links, or fenced code blocks.

Use semantic HTML including `<h1>`, `<h2>`, `<p>`, `<strong>`, `<em>`, `<a>`, `<ul>`, `<ol>`, and `<li>`.

Correct:

```json
{
  "name": "Introduction",
  "type": "nf-text",
  "content": "<h1>Contact us</h1><p>Send us a message and we will get back to you.</p>"
}
```

Incorrect:

```json
{
  "name": "Introduction",
  "type": "nf-text",
  "content": "# Contact us\n\nSend us a message."
}
```

## Form quality

- Validate before every creation or save. Correct validation errors and every quality warning marked `blocking`, then retry once; do not create or patch after a validation failure or describe a recoverable field error as a generic server error.
- Use only documented enum values. For example, `border_radius` is `none`, `small`, or `full`; never invent aliases such as `medium` or `large`.
- Infer purpose, audience, language, and tone. Make conservative assumptions instead of blocking on optional details.
- Keep short forms short. Begin a non-trivial form with one concise `nf-text` heading and supporting sentence.
- Use the most specific field type. A message or other long answer is `type: text` with `multi_lines: true`; `textarea` is not a valid type.
- Add placeholders only for useful examples or expected formats. Never use them instead of visible labels or merely repeat the label.
- Add help text only for a constraint, unfamiliar information, formatting guidance, or why data is requested.
- Require only essential fields. Do not invent consent, sensitive questions, promises, response times, or brand assets.
- In classic mode, pair at most two related short fields on a row and keep long answers full width.
- In focused mode, every visible block is already one step. Use full-width blocks and no `nf-page-break` or standalone media blocks.
- To illustrate a field or focused step, attach an `image` object directly to that input or `nf-text` block.
- Use a contextual action such as `Send message` or `Request a quote`, plus a specific completion message.

Use only durable public HTTPS media URLs. Never persist localhost, private addresses, temporary tunnels, or expiring signed URLs.
