<?php

declare(strict_types=1);

namespace Bitrix24\Lib\News\Entity;

enum NewsStatus: string
{
    case draft = 'draft';
    case published = 'published';
    case deleted = 'deleted';
}
