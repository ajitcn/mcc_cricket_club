# MCC Coding Standard

This is a mandatory project-wide engineering standard. It applies to all new
code and every modified section of existing code.

## Readable multi-line code

Application code must never be compressed into single physical lines when it
contains multiple statements, control flow, parameters, attributes, HTML
elements, CSS declarations, or JavaScript logic.

This rule applies to PHP, HTML, view templates, CSS, JavaScript, routes,
configuration, migrations, models, services, controllers, and tests. Readable
formatting takes priority over minimizing line count.

When modifying existing compressed code, format the affected section into the
readable project style where safe. Do not perform unrelated mass reformatting.

## PHP

- Put classes, methods, control structures, and exception branches on separate
  lines with braces on their own lines.
- Use one logical statement per line.
- Break long method calls, chains, query-builder expressions, SQL, arrays,
  route definitions, and assertions into readable lines.
- Keep exception handling and validation visually separated.
- Use vertical formatting for arrays with multiple entries.
- Preserve existing indentation, naming, behavior, and architecture.

## HTML and PHP views

- Keep HTML hierarchy indented and visually structured.
- Do not put an entire conditional, loop, form, table, or template section on
  one line.
- Keep PHP control structures visually separated from the HTML they render.
- Use shared layouts, components, classes, and escaping conventions.

## CSS

- Use one selector block per readable section.
- Put one declaration on each line.
- Separate logical rule groups and media queries with blank lines.
- Break long selectors when that improves readability.
- Keep custom properties and responsive rules readable.

## JavaScript

- Use one logical statement per line.
- Format functions, callbacks, event handlers, conditionals, and DOM updates
  across readable lines.
- Break long calls and chained expressions when needed.
- Do not compress event handlers or control flow.

## Long lines and completion review

Break lines that are difficult to read, especially method calls, arguments,
arrays, query builders, HTML attributes, JavaScript conditions, CSS selectors,
and test assertions.

Before completing a task, inspect every changed source file and confirm:

1. No newly added compressed multi-statement lines exist.
2. Control structures and calls are readable and properly indented.
3. HTML hierarchy is structured.
4. CSS declarations are one per line.
5. JavaScript handlers and functions are structured.
6. No block was compressed merely to save lines.
7. Syntax checks pass for affected languages where tooling is available.
8. Existing conventions and business behavior are preserved.

Formatting must not change business behavior, authorization, validation,
security, route semantics, or service authority.
