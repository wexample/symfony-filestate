<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

use ReflectionClass;

/**
 * Gives an entity page controller the route that renders its edit form.
 *
 * The form and its processor are whole files, which filestate creates on its
 * own. A controller exists for other reasons long before a form does, so the
 * method has to be grafted into it — and that is what needs the kernel: only
 * in here is the entity loadable and its `#[EntityForm]` readable.
 */
class EntityFormControllerMethodRule extends AbstractRule
{
    private const string CONTROLLER_NAMESPACE_SUFFIX = '\\Controller\\Pages\\Entity';

    private const string ENTITY_FORM_ATTRIBUTE = 'EntityForm';

    public static function getName(): string
    {
        return 'entity_form_controller_method';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^namespace\s+(\S+);/m', $content, $namespace)) {
            return $content;
        }

        if (! str_ends_with($namespace[1], self::CONTROLLER_NAMESPACE_SUFFIX)) {
            return $content;
        }

        if (! preg_match('/^\s*(?:final\s+)?class\s+(\w+)Controller\b/m', $content, $class)) {
            return $content;
        }

        if (preg_match('/function\s+edit\s*\(/', $content)) {
            return $content;
        }

        $controllerClass = $namespace[1].'\\'.$class[1].'Controller';

        // `renderPage()` is what the grafted body calls; a controller without
        // it is not one of ours.
        if (! class_exists($controllerClass) || ! method_exists($controllerClass, 'renderPage')) {
            return $content;
        }

        $entity = $class[1];
        $root = substr($namespace[1], 0, -strlen(self::CONTROLLER_NAMESPACE_SUFFIX));
        $entityClass = $root.'\\Entity\\'.$entity;

        if (! class_exists($entityClass) || ! $this->declaresPlainEntityForm($entityClass)) {
            return $content;
        }

        foreach ([
            'Symfony\\Component\\Form\\FormInterface',
            'Symfony\\Component\\HttpFoundation\\Response',
            'Symfony\\Component\\Routing\\Attribute\\Route',
            'Wexample\\SymfonyForms\\Attribute\\EntityFormProcessor',
            $entityClass,
            $root.'\\Service\\FormProcessor\\'.$entity.'FormProcessor',
        ] as $className) {
            $content = $this->importClass($content, $className);
        }

        $argument = lcfirst($entity).'Form';

        $method = <<<PHP
    #[EntityFormProcessor({$entity}FormProcessor::class, {$entity}::class)]
    #[Route(name: self::ROUTE_EDIT, path: '{id}/'.self::ROUTE_EDIT, options: self::ROUTE_OPTIONS_ONLY_EXPOSE)]
    public function edit(
        FormInterface \${$argument}
    ): Response {
        return \$this->renderPage(self::ROUTE_EDIT, [
            'form_edit' => \${$argument}->createView(),
        ]);
    }
PHP;

        // One blank line between members, none right after the class brace.
        $glue = preg_match('/\{\s*\n\s*\}\s*$/', $content) ? "\n" : "\n\n";

        return preg_replace(
            '/\n}\s*$/',
            $glue.$method."\n}\n",
            $this->declareRouteConstant($content),
            1
        );
    }

    /**
     * Whether the entity asks for the one form this rule knows how to serve.
     *
     * A named `#[EntityForm]` moves both class names and means a page of its
     * own, so only the plain edit form is grafted here.
     */
    private function declaresPlainEntityForm(string $entityClass): bool
    {
        foreach ((new ReflectionClass($entityClass))->getAttributes() as $attribute) {
            $parts = explode('\\', $attribute->getName());

            if (end($parts) === self::ENTITY_FORM_ATTRIBUTE) {
                return $attribute->getArguments() === [];
            }
        }

        return false;
    }

    private function declareRouteConstant(string $content): string
    {
        if (str_contains($content, 'ROUTE_EDIT')) {
            return $content;
        }

        return preg_replace(
            '/^(\s*(?:final\s+)?class\s+\w+Controller\b[^{]*\{)/m',
            "$1\n    final public const string ROUTE_EDIT = self::DEFAULT_ROUTE_NAME_EDIT;\n",
            $content,
            1
        );
    }
}
