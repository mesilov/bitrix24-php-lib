<?php

declare(strict_types=1);

namespace Bitrix24\Lib\Bitrix24Partners\Import;

enum SyncMode: string
{
    case Full = 'full';
    case Partial = 'partial';
}
