<?php

namespace App\MicroServices\IdGenerator;

/**
 * | Created On-04-09-2023 
 * | Created By-Bikash Kumar
 * | Interface for the Id Generation Service
 */
interface iIdGenerator
{
    public function generate(): string;
}
