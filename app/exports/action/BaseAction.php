<?php

namespace app\exports\action;

use app\exports\generator\FromGenerator;
use Generator;
use app\exports\FromArray;

abstract class BaseAction
{
    public $type;

    public $ext = 'csv';

    public $name = '导出';

    public $total = 0;

    final public function getColumn()
    {
        return app('Export')->getColumn($this->type);
    }

    public function getResult($columns,$request,$header): Generator
    {
        [$header,$columns] = $this->preResult($header,$columns);
        yield $header;
        foreach ($this->getData($request) as $data_one) {
            $temp = [];
            foreach ($columns as $value) {
                $temp[$value] = $data_one[$value];
            }
            $this->total++;
            yield $temp;
        }
    }

    abstract public function getData($request): Generator;

    public function preResult($header,$columns): array
    {
        return [$header,$columns];
    }


    public function getFromGenerator($data)
    {
        return new FromGenerator($data);
    }

    public function getName()
    {
        return $this->name;
    }

    public function getExt()
    {
        return $this->ext;
    }

    public function preValidate(): bool
    {
        return true;
    }

}
