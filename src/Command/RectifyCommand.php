<?php

namespace Wexample\SymfonyFilestate\Command;

use JsonException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Wexample\SymfonyFilestate\Service\RectifyService;
use Wexample\SymfonyFilestate\WexampleSymfonyFilestateBundle;
use Wexample\SymfonyHelpers\Command\AbstractBundleCommand;
use Wexample\SymfonyHelpers\Service\BundleService;

/**
 * Single entry point queried by the Python filestate package.
 *
 * Reads `{"rules": [...], "paths": [...]}` on stdin, writes
 * `{"files": {path: content}, "violations": [...]}` on stdout.
 * Only the changed files are returned, and nothing is written to disk:
 * filestate stays the only writer.
 */
class RectifyCommand extends AbstractBundleCommand
{
    protected static $defaultDescription = 'Compute the expected content of files, as JSON on stdin/stdout.';

    public function __construct(
        BundleService $bundleService,
        private readonly RectifyService $rectifyService,
        string $name = null,
    ) {
        parent::__construct($bundleService, $name);
    }

    public static function getBundleClassName(): string
    {
        return WexampleSymfonyFilestateBundle::class;
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        try {
            $payload = $this->readPayload();
            $result = $this->rectifyService->rectify(
                $payload['rules'],
                $payload['paths']
            );
        } catch (\Throwable $e) {
            $output->writeln(json_encode(['error' => $e->getMessage()]));

            return Command::FAILURE;
        }

        // Cast keeps an empty map encoded as `{}` rather than `[]`.
        $result['files'] = (object) $result['files'];

        $output->writeln(json_encode($result, JSON_UNESCAPED_SLASHES));

        return Command::SUCCESS;
    }

    /**
     * @return array{rules: string[], paths: string[]}
     *
     * @throws JsonException
     */
    private function readPayload(): array
    {
        $raw = stream_get_contents(STDIN);

        if ($raw === false || trim($raw) === '') {
            throw new JsonException('Expected a JSON payload on stdin, got nothing.');
        }

        $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

        return [
            'rules' => $payload['rules'] ?? [],
            'paths' => $payload['paths'] ?? [],
        ];
    }
}
