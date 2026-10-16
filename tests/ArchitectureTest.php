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
 * Tests follow the same rules as the code they belong to.
 */
#[CoversNothing]
class ArchitectureTest extends TestCase
{
    private const string ROOT_NAMESPACE = 'ByCerfrance\\LlmApiLib\\';
    private const string TESTS_NAMESPACE = 'ByCerfrance\\LlmApiLib\\Tests\\';

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

    /** @return iterable<string, array{string}> Source and test files, relative to the project root. */
    public static function phpFiles(): iterable
    {
        foreach (['src', 'tests'] as $directory) {
            $root = dirname(__DIR__) . '/' . $directory;
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if ($file->getExtension() === 'php') {
                    $relative = $directory . '/' . str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                    yield $relative => [$relative];
                }
            }
        }
    }

    #[DataProvider('phpFiles')]
    public function testModuleDependencies(string $file): void
    {
        $class = str_replace('/', '\\', substr($file, strpos($file, '/') + 1, -4));
        $module = $this->moduleOf($class);
        $violations = [];
        foreach ($this->referencedNames(dirname(__DIR__) . '/' . $file) as $name) {
            foreach (self::OPTIONAL_DEPENDENCIES as $prefix => $owner) {
                if (str_starts_with($name, $prefix) && $owner !== $module) {
                    $violations[] = $name;
                }
            }
            if (!str_starts_with($name, self::ROOT_NAMESPACE)) {
                continue;
            }
            $relative = substr($name, strlen(str_starts_with($name, self::TESTS_NAMESPACE) ? self::TESTS_NAMESPACE : self::ROOT_NAMESPACE));
            $target = $this->moduleOf($relative);
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
