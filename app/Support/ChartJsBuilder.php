<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Minimal replacement for fx3costa/laravelchartjs (not compatible with Laravel 11).
 */
class ChartJsBuilder
{
    private array $charts = [];

    private ?string $name = null;

    private array $defaults = [
        'datasets' => [],
        'labels' => [],
        'type' => 'line',
        'options' => [],
        'size' => ['width' => null, 'height' => null],
    ];

    /** @var string[] */
    private array $types = [
        'bar', 'horizontalBar', 'bubble', 'scatter', 'doughnut',
        'line', 'pie', 'polarArea', 'radar',
    ];

    public function name(string $name): self
    {
        $this->name = $name;
        $this->charts[$name] = $this->defaults;

        return $this;
    }

    public function element(string $element): self
    {
        return $this->set('element', $element);
    }

    /** @param  array<int, string>  $labels */
    public function labels(array $labels): self
    {
        return $this->set('labels', $labels);
    }

    public function datasets(array $datasets): self
    {
        return $this->set('datasets', $datasets);
    }

    public function type(string $type): self
    {
        if (! in_array($type, $this->types, true)) {
            throw new \InvalidArgumentException('Invalid Chart type.');
        }

        return $this->set('type', $type);
    }

    /** @param  array{width?: int|null, height?: int|null}  $size */
    public function size(array $size): self
    {
        return $this->set('size', $size);
    }

    public function options(array $options): self
    {
        foreach ($options as $key => $value) {
            $this->set('options.'.$key, $value);
        }

        return $this;
    }

    public function optionsRaw(string|array $optionsRaw): self
    {
        if (is_array($optionsRaw)) {
            $this->set('optionsRaw', json_encode($optionsRaw, JSON_THROW_ON_ERROR));

            return $this;
        }

        $this->set('optionsRaw', $optionsRaw);

        return $this;
    }

    public function render()
    {
        $chart = $this->charts[$this->name];

        return view('charts.chart-template')
            ->with('datasets', $chart['datasets'])
            ->with('element', $this->name)
            ->with('labels', $chart['labels'])
            ->with('options', isset($chart['options']) ? $chart['options'] : '')
            ->with('optionsRaw', $chart['optionsRaw'] ?? '')
            ->with('type', $chart['type'])
            ->with('size', $chart['size']);
    }

    private function set(string $key, mixed $value): self
    {
        Arr::set($this->charts[$this->name], $key, $value);

        return $this;
    }
}
