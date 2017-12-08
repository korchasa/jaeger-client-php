<?php
declare(strict_types=1);

namespace Jaeger\Tracer;

use Ds\Stack;
use Jaeger\Client\ThriftClient;
use Jaeger\Id\RandomIntGenerator;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Sampler\ProbabilisticSampler;
use Jaeger\Span\Factory\SpanFactory;
use Jaeger\Thrift\Agent\AgentClient;
use Jaeger\Transport\TUDPTransport;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Transport\TBufferedTransport;

class Config
{
    private $defaults = [
        'agent_host' => 'localhost',
        'agent_port' => 6831,
        'sample' => 0.01,
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
        $stack = new Stack();
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
        if (is_bool($config['sample'])) {
            return new ConstSampler($config['sample']);
        } elseif (is_float($config['sample'])) {
            return new ProbabilisticSampler($config['sample']);
        } else {
            throw new \InvalidArgumentException('sample option must be a boolean or float');
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