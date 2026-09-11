<?php

namespace App\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class ProcedureRuleBadges extends Component
{
    /**
     * Create a new component instance.
     */
    public function __construct(
        public bool $videosurgery = false,
        public ?string $rule = null,
        public bool $unique = true,
    ) {
    }

    public function badges(): array
    {
        $badges = [];

        if ($this->videosurgery) {
            $badges[] = [
                'key' => 'videosurgery',
                'label' => __('Video'),
                'color' => 'blue',
            ];
        }

        // Regla de pricing
        if ($this->rule) {
            $badges[] = match ($this->rule) {
                'night_rate' => ['key' => 'night', 'label' => __('Night'), 'color' => 'violet'],
                'long_case_rate' => ['key' => 'long', 'label' => __('Long'), 'color' => 'yellow'],
                'default_rate' => ['key' => 'std', 'label' => __('Standard'), 'color' => 'accent'],
                'video_rate' => ['key' => 'video', 'label' => __('Video'), 'color' => 'blue'],
                default => ['key' => 'std', 'label' => __('Standard'), 'color' => 'accent'],
            };
        }

        if (empty($badges)) {
            $badges[] = ['key' => 'std', 'label' => __('Standard'), 'color' => 'accent'];
        }

        if ($this->unique) {
            $badges = collect($badges)->unique('key')->all();
        }

        return $badges;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.procedure-rule-badges');
    }
}
