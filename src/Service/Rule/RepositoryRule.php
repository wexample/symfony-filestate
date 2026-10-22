<?php

namespace Wexample\SymfonyFilestate\Service\Rule;

class RepositoryRule extends AbstractRule
{
    private const ABSTRACT_REPOSITORY_IMPORT = "use Wexample\\SymfonyHelpers\\Repository\\AbstractRepository;\n";

    private const MANAGER_REGISTRY_IMPORT = "use Doctrine\\Persistence\\ManagerRegistry;\n";

    private const SERVICE_ENTITY_REPOSITORY_IMPORT = "use Doctrine\\Bundle\\DoctrineBundle\\Repository\\ServiceEntityRepository;\n";

    public static function getName(): string
    {
        return 'repository';
    }

    public function rectify(
        string $path,
        string $content
    ): string {
        if (! preg_match('/^\s*(?:final\s+|abstract\s+)?class\s+(\w+)\s+extends\s+(\w+)/m', $content, $matches)) {
            return $content;
        }

        [, $className, $parentName] = $matches;

        if ($parentName === 'ServiceEntityRepository') {
            $content = $this->extendAbstractRepository($content, $className);
        } elseif ($parentName !== 'AbstractRepository') {
            return $content;
        }

        return $this->removeStaleServiceEntityRepositoryReferences($content);
    }

    private function extendAbstractRepository(
        string $content,
        string $className
    ): string {
        $content = $this->addImport($content, self::ABSTRACT_REPOSITORY_IMPORT);

        $content = preg_replace(
            '/\bclass\s+'.preg_quote($className, '/').'\s+extends\s+ServiceEntityRepository\b/m',
            'class '.$className.' extends AbstractRepository',
            $content,
            1
        );

        $content = $this->removeMakerBodyIfUntouched($content, $className);

        return $this->removeImportIfUnused(
            $content,
            self::MANAGER_REGISTRY_IMPORT,
            'ManagerRegistry'
        );
    }

    /**
     * A body the developer already edited is left alone: only the untouched
     * maker-bundle boilerplate, whitespace aside, can be dropped.
     */
    private function removeMakerBodyIfUntouched(
        string $content,
        string $className
    ): string {
        if (! preg_match('/parent::__construct\(\s*\$registry\s*,\s*(\w+)::class\s*\)/', $content, $matches)) {
            return $content;
        }

        $entityShortName = $matches[1];
        $alias = strtolower($entityShortName[0]);

        $pattern = '/(class\s+'.preg_quote($className, '/').'\b[^{]*\{)(?<body>[\s\S]*?)(^\})/m';
        if (! preg_match($pattern, $content, $blockMatches)) {
            return $content;
        }

        $normalizedBody = preg_replace('/\s+/', '', $blockMatches['body']);
        $normalizedMakerBody = preg_replace('/\s+/', '', <<<PHP
public function __construct(ManagerRegistry \$registry)
{
    parent::__construct(\$registry, {$entityShortName}::class);
}

//    /**
//     * @return {$entityShortName}[] Returns an array of {$entityShortName} objects
//     */
//    public function findByExampleField(\$value): array
//    {
//        return \$this->createQueryBuilder('{$alias}')
//            ->andWhere('{$alias}.exampleField = :val')
//            ->setParameter('val', \$value)
//            ->orderBy('{$alias}.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField(\$value): ?{$entityShortName}
//    {
//        return \$this->createQueryBuilder('{$alias}')
//            ->andWhere('{$alias}.exampleField = :val')
//            ->setParameter('val', \$value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
PHP);

        if ($normalizedBody !== $normalizedMakerBody) {
            return $content;
        }

        return preg_replace($pattern, '$1'."\n".'$3', $content, 1);
    }

    private function removeStaleServiceEntityRepositoryReferences(
        string $content
    ): string {
        $content = preg_replace(
            '/^[ \t]*\*[ \t]*@extends[ \t]+ServiceEntityRepository<[^>]*>[ \t]*\n/m',
            '',
            $content,
            1,
            $removedAnnotations
        );

        if ($removedAnnotations > 0) {
            $content = preg_replace('/^\/\*\*[ \t]*\n[ \t]*\*\/\n/m', '', $content, 1);
        }

        return $this->removeImportIfUnused(
            $content,
            self::SERVICE_ENTITY_REPOSITORY_IMPORT,
            'ServiceEntityRepository'
        );
    }

    private function addImport(
        string $content,
        string $importLine
    ): string {
        if (str_contains($content, $importLine)) {
            return $content;
        }

        // Joining the existing block keeps the file sortable by php-cs-fixer.
        if (preg_match_all('/^use\s+[^;]+;\n/m', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $lastImport = end($matches[0]);

            return substr_replace(
                $content,
                $importLine,
                $lastImport[1] + strlen($lastImport[0]),
                0
            );
        }

        return preg_replace(
            '/^namespace\s+[^;]+;\n/m',
            "$0\n".$importLine,
            $content,
            1
        );
    }

    private function removeImportIfUnused(
        string $content,
        string $importLine,
        string $shortName
    ): string {
        $withoutImport = str_replace($importLine, '', $content);

        if (preg_match('/\b'.preg_quote($shortName, '/').'\b/', $withoutImport)) {
            return $content;
        }

        return $withoutImport;
    }
}
