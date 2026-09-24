<?php

namespace App\Domain\Topics;

use App\Models\TopicSource;
use Carbon\Carbon;

interface TopicSourceConnector
{
    /** @return list<array{source_key:string,title:string,summary:?string,url:?string,published_at:?Carbon,raw_payload?:array}> */
    public function collect(TopicSource $source): array;
}
