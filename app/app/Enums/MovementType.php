<?php

namespace App\Enums;

enum MovementType: string
{
    case Push = 'push';
    case Pull = 'pull';
    case Legs = 'legs';
    case Core = 'core';
}
