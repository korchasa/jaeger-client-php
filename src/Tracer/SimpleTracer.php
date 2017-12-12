<?php
declare(strict_types=1);

namespace Jaeger\Tracer;

use Jaeger\Client\ClientInterface;
use Jaeger\Codec\TextCodec;
use Jaeger\Log\ErrorLog;
use Jaeger\Log\UserLog;
use Jaeger\Span\Context\SpanContext;
use Jaeger\Span\Factory\SpanFactoryInterface;
use Jaeger\Span\SpanInterface;
use Jaeger\Tag\BoolTag;
use Jaeger\Tag\LongTag;
use Jaeger\Tag\StringTag;

class SimpleTracer implements FlushableInterface
{
    const BACKTRACE_STRING = 10;
    const MAX_TAG_SYMBOLS = 50;
    const MAX_ERROR_SYMBOLS = 50;

    /** @var \SplStack|SpanInterface[] */
    private $stack;
    /** @var SpanFactoryInterface */
    private $factory;
    /** @var ClientInterface */
    private $client;
    /** @var SpanContext */
    private $carrierContext;

    /**
     * SimpleTracer constructor.
     * @param \SplStack $stack
     * @param SpanFactoryInterface $factory
     * @param ClientInterface $client
     */
    public function __construct(\SplStack $stack, SpanFactoryInterface $factory, ClientInterface $client)
    {
        $this->stack = $stack;
        $this->factory = $factory;
        $this->client = $client;
    }

    public function flush(): FlushableInterface
    {
        $this->client->flush();

        return $this;
    }

    public function getCurrent(): ?SpanInterface
    {
        if (0 === $this->stack->count()) {
            return null;
        }

        return $this->stack->top();
    }

    public function start(string $operationName, SpanContext $overrideContext = null): SimpleTracer
    {
        if ($overrideContext) {
            $context = $overrideContext;
        } else if ($this->getCurrent()) {
            $context = $this->getCurrent()->getContext();
        } else {
            $context = $this->carrierContext;
            $this->carrierContext = null;
        }
        $span = $this->factory->create($operationName, [], $context);
        $this->stack->push($span);

        return $this;
    }

    public function finish(int $duration = 0): SimpleTracer
    {
        /** @var SpanInterface $currentSpan */
        $currentSpan = $this->stack->pop();
        $this->client->add($currentSpan->finish($duration));

        return $this;
    }

    public function spanIt(string $operationName, callable $payload)
    {
        $this->start($operationName);
        $payload($this);
        $this->finish();
    }

    /**
     * @param string $format
     * @return mixed|null
     * @throws \Exception
     */
    public function buildCarrier(string $format)
    {
        if ($format == 'text') {
            $carrier = (new TextCodec())->encode($this->getCurrent()->getContext());
        } else {
            throw new \Exception("not support format $format");
        }

        return $carrier;
    }

    /**
     * @param string $format
     * @param mixed $carrier
     * @throws \Exception
     */
    public function applyCarrier(string $format, $carrier)
    {
        if ($format == 'text') {
            $this->carrierContext = (new TextCodec())->decode($carrier);
            if (!$this->carrierContext) {
                throw new \InvalidArgumentException("Can't decode carrier `$carrier`");
            }
        } else {
            throw new \InvalidArgumentException("not support format $format");
        }
    }

    /**
     * @param string $key
     * @param $value
     * @param string|null $tagClass
     * @return $this
     */
    public function tag(string $key, $value, string $tagClass = null)
    {
        $tagClass = $tagClass ?? $this->resolveTagClass($value);
        $this->getCurrent()->addTag(new $tagClass($key, $value));

        return $this;
    }

    public function log(string $message, string $level = 'DEBUG')
    {
//        var_dump($message);
        $this->getCurrent()->addLog(new UserLog(
            $level,
$message
//            $this->reduceLength($message, static::MAX_TAG_SYMBOLS - mb_strlen($level))
        ));

        return $this;
    }

    public function error(string $message)
    {
        $this->getCurrent()->addLog(new ErrorLog(
            $this->reduceLength($message, static::MAX_TAG_SYMBOLS - 10),
            ''
        ));

        return $this;
    }

    private function resolveTagClass($value)
    {
        if (is_bool($value)) {
            return BoolTag::class;
        } else if (is_string($value)) {
            return StringTag::class;
        } else if(is_integer($value)) {
            return LongTag::class;
        }
    }

    private function reduceLength(string $string, int $maxLength): string
    {
        if (mb_strlen($string) < ($maxLength - 3)) {
            return $string;
        } else {
            $a= mb_substr($string, 0, $maxLength - 3).'...';
            var_dump($a);
            return $a;
        }
    }

}
