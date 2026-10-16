<?php

declare(strict_types=1);

namespace ByCerfrance\LlmApiLib\Tests;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guards the module boundaries that allow extracting packages later.
 *
 * The core never depends on an integration; providers only share the OpenAI-compatible protocol.
 */
#[CoversNothing]
class ArchitectureTest extends TestCase
{
    private const string ROOT_NAMESPACE = 'ByCerfrance\\LlmApiLib\\';

    /** Namespace prefix => module. Unlisted namespaces belong to the core. */
    private const array MODULES = [
        'Provider\\OpenAiCompatible\\' => 'openai-compatible',
        'Provider\\OpenAi\\' => 'openai',
        'Provider\\Google\\' => 'google',
        'Provider\\Mistral\\' => 'mistral',
        'Provider\\Ovh\\' => 'ovh',
        'Mcp\\' => 'mcp',
        'OpenApi\\' => 'openapi',
    ];

    /** Module => modules it may depend on, besides itself. */
    private const array ALLOWED = [
        'core' => [],
        'openai-compatible' => ['core'],
        'openai' => ['core', 'openai-compatible'],
        'google' => ['core', 'openai-compatible'],
        'mistral' => ['core', 'openai-compatible'],
        'ovh' => ['core', 'openai-compatible'],
        'mcp' => ['core'],
        'openapi' => ['core'],
    ];

    /** Optional third-party namespace => only module allowed to use it. */
    private const array OPTIONAL_DEPENDENCIES = [
        'cebe\\openapi\\' => 'openapi',
    ];

    /** @return iterable<string, array{string}> */
    public static function sourceFiles(): iterable
    {
        $root = dirname(__DIR__) . '/src';
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                yield $relative => [$relative];
            }
        }
    }

    #[DataProvider('sourceFiles')]
    public function testModuleDependencies(string $file): void
    {
        $class = str_replace('/', '\\', substr($file, 0, -4));
        $module = $this->moduleOf($class);
        $violations = [];
        foreach ($this->referencedNames(dirname(__DIR__) . '/src/' . $file) as $name) {
            foreach (self::OPTIONAL_DEPENDENCIES as $prefix => $owner) {
                if (str_starts_with($name, $prefix) && $owner !== $module) {
                    $violations[] = $name;
                }
            }
            if (!str_starts_with($name, self::ROOT_NAMESPACE)) {
                continue;
            }
            $target = $this->moduleOf(substr($name, strlen(self::ROOT_NAMESPACE)));
            if ($target !== $module && !in_array($target, self::ALLOWED[$module], true)) {
                $violations[] = sprintf('%s (%s)', $name, $target);
            }
        }

        $this->assertSame([], $violations, sprintf('Module "%s" (%s) has forbidden dependencies.', $module, $file));
    }

    private function moduleOf(string $relativeClass): string
    {
        foreach (self::MODULES as $prefix => $module) {
            if (str_starts_with($relativeClass, $prefix)) {
                return $module;
            }
        }

        return 'core';
    }

    /** @return list<string> Imported and fully qualified names. */
    private function referencedNames(string $path): array
    {
        $names = [];
        $tokens = token_get_all(file_get_contents($path));
        $depth = 0;
        foreach ($tokens as $index => $token) {
            if ($token === '{') {
                $depth++;
            } elseif ($token === '}') {
                $depth--;
            }
            if (!is_array($token)) {
                continue;
            }
            if ($token[0] === T_CURLY_OPEN || $token[0] === T_DOLLAR_OPEN_CURLY_BRACES) {
                $depth++;
            }
            if ($token[0] === T_NAME_FULLY_QUALIFIED) {
                $names[] = ltrim($token[1], '\\');
            }
            if ($token[0] === T_USE && $depth === 0) {
                for ($next = $index + 1; isset($tokens[$next]) && $tokens[$next] !== ';'; $next++) {
                    if (is_array($tokens[$next]) && $tokens[$next][0] === T_NAME_QUALIFIED) {
                        $names[] = $tokens[$next][1];
                    }
                }
            }
        }

        return $names;
    }
}
