<?php
declare(strict_types=1);

namespace Jaeger\Tracer;

use Jaeger\Client\ClientInterface;
use Jaeger\Codec\TextCodec;
use Jaeger\Span\Context\ContextAwareInterface;
use Jaeger\Span\Context\SpanContext;
use Jaeger\Span\Factory\SpanFactoryInterface;
use Jaeger\Span\SpanInterface;

class Tracer implements TracerInterface, ContextAwareInterface, InjectableInterface, FlushableInterface
{
    private $context;

    private $stack;

    private $factory;

    private $client;

    public function __construct(\SplStack $stack, SpanFactoryInterface $factory, ClientInterface $client)
    {
        $this->stack = $stack;
        $this->factory = $factory;
        $this->client = $client;
    }

    public function flush(): FlushableInterface
    {
        if (0 !== $this->stack->count()) {
            trigger_error(
                'You are flushing non-empty tracer stack, some span(-s) were started but not finished',
                E_WARNING
            );
        }
        $this->client->flush();

        return $this;
    }

    public function assign(SpanContext $context): InjectableInterface
    {
        $this->context = $context;

        return $this;
    }

    public function getContext(): ?SpanContext
    {
        return $this->context;
    }

    /**
     * @param SpanContext $span
     * @param string $format
     * @param mixed $carrier
     * @return mixed|null
     * @throws \Exception
     */
    public function inject(SpanContext $span, string $format, &$carrier = null)
    {
        if ($format == 'text') {
            $carrier = (new TextCodec())->encode($span);
        } else {
            throw new \Exception("not support format $format");
        }

        return $carrier;
    }

    /**
     * @param string $format
     * @param mixed $carrier
     * @return SpanContext|null
     * @throws \Exception
     */
    public function extract(string $format, $carrier): ?SpanContext
    {
        if ($format == 'text') {
            return (new TextCodec())->decode($carrier);
        } else {
            throw new \Exception("not support format $format");
        }
    }

    public function start(string $operationName, array $tags = [], SpanContext $context = null): SpanInterface
    {
        $span = $this->factory->create($operationName, $tags, $context ?? $this->context);
        $this->stack->push($span->getContext());
        $this->context = $this->stack->top();

        return $span;
    }

    public function finish(SpanInterface $span, int $duration = 0): TracerInterface
    {
        $this->client->add($span->finish($duration));
        $this->stack->pop();
        $this->context = $this->stack->count() ? $this->stack->top() : null;

        return $this;
    }
}
