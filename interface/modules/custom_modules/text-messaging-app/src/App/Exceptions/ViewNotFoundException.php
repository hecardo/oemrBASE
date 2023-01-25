<?php
/*
 *  package OpenEMR
 *  link    https://www.open-emr.org
 *  author  Sherwin Gaddis <sherwingaddis@gmail.com>
 *  Copyright (c) 2022.
 *  license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

namespace Juggernaut\App\Exceptions;

class ViewNotFoundException extends \Exception
{

    public function __construct()
    {
        //doepic stuff
    }

    protected $message = 'View Not found';
}
