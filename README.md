# symfony-filestate

Version: 4.0.1

`symfony-filestate` is a Symfony bundle that acts as the PHP backend for the Python filestate package: it accepts a list of rule names and file paths as JSON on stdin, applies each rule's `rectify()` method to compute what each file's content should be, and returns only the changed files plus a list of violations as JSON on stdout — the bundle never writes to disk itself. Rules extend `AbstractRule` and are auto-wired as Symfony services tagged `wexample.filestate.rule`; the bundle ships no rule of its own. It is intended for Symfony applications that enforce source-file conventions through the filestate toolchain, where the Python layer owns all actual writes to disk.

## Table of Contents

- [Architecture](#architecture)
- [Integration in the Suite](#integration-in-the-suite)
- [Dependencies](#dependencies)
- [Versioning & Compatibility Policy](#versioning--compatibility-policy)
- [License](#license)
- [About us](#about-us)
- [Migration Notes](#migration-notes)

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

The bundle ships no rule of its own.

### Call path

```
Python filestate
  │  stdin: {"rules": ["rule_name"], "paths": ["/…/Entity.php"]}
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

## Integration in the Suite

This package is part of the Wexample Suite — a collection of high-quality, modular tools designed to work seamlessly together across multiple languages and environments.

### Related Packages

The suite includes packages for configuration management, file handling, prompts, and more. Each package can be used independently or as part of the integrated suite.

Visit the [Wexample Suite documentation](https://docs.wexample.com) for the complete package ecosystem.

## Dependencies

- php: >=8.2
- wexample/symfony-helpers: >=7.0.0

## Versioning & Compatibility Policy

Wexample packages follow **Semantic Versioning** (SemVer):

- **MAJOR**: Breaking changes
- **MINOR**: New features, backward compatible
- **PATCH**: Bug fixes, backward compatible

We maintain backward compatibility within major versions and provide clear migration guides for breaking changes.

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

Free to use in both personal and commercial projects.

## About us

[Wexample](https://wexample.com) stands as a cornerstone of the digital ecosystem — a collective of seasoned engineers, researchers, and creators driven by a relentless pursuit of technological excellence. More than a media platform, it has grown into a vibrant community where innovation meets craftsmanship, and where every line of code reflects a commitment to clarity, durability, and shared intelligence.

This packages suite embodies this spirit. Trusted by professionals and enthusiasts alike, it delivers a consistent, high-quality foundation for modern development — open, elegant, and battle-tested. Its reputation is built on years of collaboration, refinement, and rigorous attention to detail, making it a natural choice for those who demand both robustness and beauty in their tools.

Wexample cultivates a culture of mastery. Each package, each contribution carries the mark of a community that values precision, ethics, and innovation — a community proud to shape the future of digital craftsmanship.

## Migration Notes

When upgrading between major versions, refer to the migration guides in the documentation.

Breaking changes are clearly documented with upgrade paths and examples.
