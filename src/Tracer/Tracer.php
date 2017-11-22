<?php
declare(strict_types=1);

namespace CodeTool\OpenTracing\Tracer;

use CodeTool\OpenTracing\Client\ClientInterface;
use CodeTool\OpenTracing\General\PhpBinaryTag;
use CodeTool\OpenTracing\General\PhpVersionTag;
use CodeTool\OpenTracing\General\JaegerHostnameTag;
use CodeTool\OpenTracing\General\JaegerVersionTag;
use CodeTool\OpenTracing\Span\Context\SpanContext;
use CodeTool\OpenTracing\Span\Factory\SpanFactoryInterface;
use CodeTool\OpenTracing\Span\SpanInterface;
use Ds\Stack;

class Tracer implements TracerInterface
{
    private $stack;

    private $factory;

    private $client;

    public function __construct(Stack $stack, SpanFactoryInterface $factory, ClientInterface $client)
    {
        $this->stack = $stack;
        $this->factory = $factory;
        $this->client = $client;
    }

    public function flush(): TracerInterface
    {
        $this->client->flush();

        return $this;
    }

    public function getLocalTags()
    {
        return [
            new JaegerVersionTag(),
            new JaegerHostnameTag(),
            new PhpBinaryTag(),
            new PhpVersionTag(),
        ];
    }

    public function assign(SpanContext $context): TracerInterface
    {
        $this->stack->push([$context]);

        return $this;
    }


    public function getCurrentContext(): ?SpanContext
    {
        if (0 === $this->stack->count()) {
            return null;
        }

        return $this->stack->peek();
    }

    public function start(string $operationName, array $tags = []): SpanInterface
    {
        $span = $this->factory->create(
            $operationName,
            array_merge($this->getLocalTags(), $tags),
            $this->getCurrentContext()
        );
        $this->stack->push($span->getContext());

        return $span;
    }

    public function finish(SpanInterface $span): TracerInterface
    {
        $this->client->add($span->finish());
        $this->stack->pop();

        return $this;
    }
}
