# Documentation

[Back to the project README](../README.md)

Learn the library with small, practical examples. Start with [Getting started](getting-started.md), then read [Conversations](conversations.md) and [Tools](tools.md). Each guide explains its inputs, expected results and common mistakes.

## Guides

| Guide | What you will learn |
| --- | --- |
| [Getting started](getting-started.md) | Install a client, configure credentials and get your first answer |
| [Conversations](conversations.md) | Add system instructions, continue a chat and create branches |
| [Models and options](models-and-options.md) | Declare capabilities, set options and request structured output |
| [Tools](tools.md) | Give a model a PHP function, delegate to another chat and understand the execution loop |
| [Providers](providers.md) | Choose an endpoint and separate providers from models |
| [Retries and failover](retries-and-failover.md) | Recover from transient failures and validate answers |
| [Errors and refusals](errors.md) | Find out why a call failed or was refused, and react to it |
| [Usage and pricing](usage-and-pricing.md) | Track consumption, inspect snapshots and estimate costs |
| [Images](images.md) | Generate images and edit them through native Gemini conversations |
| [Streaming](streaming.md) | Display text as it arrives and retrieve the completed response |
| [MCP](mcp.md) | Use remote tools over JSON or SSE |
| [OpenAPI](openapi.md) | Turn REST operations into tools |
| [Architecture](architecture.md) | Find a class from its responsibility and respect module boundaries |

## Running examples

The guides use a shared `bootstrap.php` described in [Getting started](getting-started.md). Save guide scripts next to that file in your own project root and run them with PHP. Blocks marked as variants reuse variables from their guide's main example.

**Guide scripts make real requests** unless marked offline. Configure a model available to your provider account. Response text and usage depend on that model; example output is illustrative.

The repository's [executable examples](../examples/README.md) run **offline by default** and are useful for learning without credentials. Image and streaming scripts provide an explicit `--live` mode. They do not load `.env` files automatically: environment variables must already be set.

## Reference and migration

- [Migrating from 1.x](../MIGRATION-2.x.md)
- [Changelog](../CHANGELOG.md)
- [Detailed execution behavior and limits](../examples/README.md)

The library uses caller-supplied model metadata rather than a built-in model catalog.
