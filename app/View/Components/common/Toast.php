<?php

namespace App\View\Components\common;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class Toast extends Component
{
    public string $type;
    public ?string $title;
    public ?string $message;

    public function __construct(string $type = 'info', ?string $title = null, ?string $message = null)
    {
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
    }

    public function render(): View|Closure|string
    {
        return view('components.common.toast');
    }
}
