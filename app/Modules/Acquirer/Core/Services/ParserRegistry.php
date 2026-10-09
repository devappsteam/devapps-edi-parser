<?php

namespace App\Modules\Acquirer\Core\Services;

use App\Modules\Acquirer\Core\Contracts\AcquirerParserInterface;
use InvalidArgumentException;

class ParserRegistry
{
    /** @var array<string, AcquirerParserInterface> */
    protected array $parsers = [];

    public function register(AcquirerParserInterface $parser): void
    {
        $this->parsers[strtoupper($parser->acquirerCode())] = $parser;
    }

    public function resolve(string $acquirerCode): AcquirerParserInterface
    {
        $code = strtoupper($acquirerCode);

        if (!isset($this->parsers[$code])) {
            throw new InvalidArgumentException("No parser registered for acquirer: [{$code}]");
        }

        return $this->parsers[$code];
    }
}
