<?php

namespace BrickServers\GoogleWorkspace\Enums;

enum UserProjection: string
{
    case BASIC = 'basic';
    case FULL = 'full';
    case CUSTOM = 'custom';
}
