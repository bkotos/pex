<?php

namespace Pex\Example;

class Company
{
    /** @var string */
    public $name;

    /** @var int */
    public $foundingYear;

    /** @var Person */
    public $ceo;

    /** @var Company */
    public $parentCompany;
}
