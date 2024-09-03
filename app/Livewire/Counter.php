<?php

namespace App\Livewire;

use Livewire\Component;

class Counter extends Component
{
    public $count = 10;
    
    public function render()
    {
        return view('livewire.counter');
    }

    public function inc(){
        $this->count++;
    }
}


