<?php

namespace Sprint\Migration\Enum;

class VersionEnum
{

    const STATUS_INSTALLED = 'installed';
    const STATUS_NEW = 'new';
    const STATUS_UNKNOWN = 'unknown';

    const ACTION_UP = 'up';
    const ACTION_DOWN = 'down';
}