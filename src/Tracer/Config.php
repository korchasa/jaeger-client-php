<?php
declare(strict_types=1);

namespace Jaeger\Tracer;

use Jaeger\Client\ThriftClient;
use Jaeger\Id\RandomIntGenerator;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use Jaeger\Span\Factory\SpanFactory;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Transport\TUDPTransport;
use SplStack;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class Config
{
    private $defaults = [
        'agent_host' => 'localhost',
        'agent_port' => 6831,
        'send_ratio' => 1,
        'buffer_size' => 16384
    ];

    /**
     * @see Config::$defaults
     * @param $serviceName
     * @param array $options
     * @return Tracer
     */
    public function create($serviceName, array $options = [])
    {
        $config = $this->config($options);
        $stack = new SplStack();
        $factory = new SpanFactory(new RandomIntGenerator(), $this->sampler($config));
        $udpTransport = new TUDPTransport($config['agent_host'], $config['agent_port']);
        $bufferedTransport = new TBufferedTransport($udpTransport, $config['buffer_size']);
        $bufferedTransport->open();
        $protocol = new TCompactProtocol($bufferedTransport);
        $agent = new AgentClient($protocol);
        $client = new ThriftClient($serviceName, $agent);
        return new Tracer($stack, $factory, $client);
    }

    private function sampler($config)
    {
        if (is_int($config['send_ratio'])) {
            return new ConstSampler((boolean) $config['send_ratio']);
        } elseif (is_float($config['send_ratio'])) {
            return new ProbabilisticSampler($config['send_ratio']);
        } else {
            throw new \InvalidArgumentException('sample option must be a int or float');
        }
    }

    private function config(array $override = [])
    {
        $delta = array_diff(array_keys($override), array_keys($this->defaults));
        if (count($delta)) {
            throw new \InvalidArgumentException(
                "Unsupported config params: ".implode(', ', $delta)
            );
        }

        return array_merge($this->defaults, $override);
    }
}