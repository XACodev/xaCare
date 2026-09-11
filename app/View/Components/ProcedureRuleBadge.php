<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProcedureRuleBadge extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public bool $videosurgery = false,
        public ?string $rule = null,
    ) {
    }

    public function badge(): array
    {
        // if ($this->videosurgery) {
        //     return [
        //         'label' => __('Video'),
        //         'color' => 'blue',
        //         'tooltip' => __('Video surgery procedure'),
        //     ];
        // }

        return match ($this->rule) {
            'default_rate' => [
                'label' => __('Standard'),
                'color' => 'accent',
                'tooltip' => __('Standard procedure'),
            ],
            'night_rate' => [
                'label' => __('Night'),
                'color' => 'violet',
                'tooltip' => __('Night procedure'),
            ],
            'long_case_rate' => [
                'label' => __('Long'),
                'color' => 'yellow',
                'tooltip' => __('Long procedure'),
            ],
            'courtesy_rate' => [
                'label' => __('Courtesy'),
                'color' => 'emerald',
                'tooltip' => __('Courtesy procedure'),
            ],
            'video_rate' => [
                'label' => __('Video'),
                'color' => 'blue',
                'tooltip' => __('Video procedure'),
            ],
            default => [
                'label' => __('Standard'),
                'color' => 'accent',
                'tooltip' => __('Standard procedure'),
            ],
        };
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.procedure-rule-badge');
    }
}
