<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Analyst = 'analyst';
    case Viewer = 'viewer';
}
