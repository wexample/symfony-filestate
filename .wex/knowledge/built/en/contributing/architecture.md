## Architecture

The bundle is the PHP backend for the Python filestate package. It receives a list of rule names and file paths as JSON on stdin, computes what each file's content should be, and returns only the changed files together with a list of violations as JSON on stdout. It never writes to disk.

### Parts

#### Bundle and wiring

src/WexampleSymfonyFilestateBundle.php is a plain Symfony `Bundle` subclass with no custom behaviour. src/DependencyInjection/WexampleSymfonyFilestateExtension.php loads src/Resources/config/services.yaml, which autowires everything under `Command\` and `Service\` and tags every class that extends `AbstractRule` with `wexample.filestate.rule`.

#### RectifyCommand

src/Command/RectifyCommand.php is the single entry point the Python layer calls. On `execute()` it reads a JSON object from `STDIN` with two keys — `rules` (an array of rule-name strings) and `paths` (an array of absolute file paths) — delegates to `RectifyService`, then writes the result back as a JSON object on stdout:

```json
{"files": {"/abs/path/File.php": "<corrected content>"}, "violations": ["rule_name: /abs/path/File.php"]}
```

`files` contains only paths whose content changed. An empty map is encoded as `{}`, not `[]`, because the command casts it with `(object)` before encoding.

#### RectifyService

src/Service/RectifyService.php owns all logic. Its constructor receives every tagged rule through Symfony's `#[TaggedIterator('wexample.filestate.rule')]`. On `rectify(ruleNames, paths)` it:

1. Resolves the requested rule names to `AbstractRule` instances via `resolveRules()`, throwing `InvalidArgumentException` for any unknown name.
2. Iterates each path; records `missing_file: <path>` in `violations` and skips if the file does not exist.
3. Applies the resolved rules in order, passing `(path, content)` to each. When a rule changes the content it records `<rule_name>: <path>` in `violations` and passes the updated content to the next rule.
4. Collects paths whose final content differs from what was read from disk into `files`.

The service is also the only place that reads from disk (`file_get_contents`); rules receive the content as a string and return a string.

#### AbstractRule

src/Service/Rule/AbstractRule.php declares the contract every rule must implement:

```php
abstract public static function getName(): string;
abstract public function rectify(string $path, string $content): string;
```

`getName()` returns the string used to request the rule from the Python side. `rectify()` receives the absolute path and the current content; it returns the content as it should be (unchanged if the rule is already satisfied). Rules never touch the filesystem.

#### HasSecureIdTraitRule

src/Service/Rule/HasSecureIdTraitRule.php is the only built-in rule, registered as `has_secure_id_trait`. Given a PHP file that contains a class definition, it:

1. Adds `use Wexample\SymfonyHelpers\Entity\Traits\HasSecureIdTrait;` after the `namespace` statement if the import is absent.
2. Adds `use HasSecureIdTrait;` inside the class body if the trait is not already used there.

If no class definition is found the file is returned unmodified.

### Call path

```
Python filestate
  │  stdin: {"rules": ["has_secure_id_trait"], "paths": ["/…/Entity.php"]}
  ▼
RectifyCommand::execute()
  │  readPayload() → {rules, paths}
  ▼
RectifyService::rectify(rules, paths)
  │  resolveRules() → [AbstractRule, …]
  │  file_get_contents(path) per path
  │  AbstractRule::rectify(path, content) per rule per path
  ▼
RectifyCommand::execute()
  │  stdout: {"files": {…}, "violations": […]}
  ▼
Python filestate (owns all writes to disk)
```
