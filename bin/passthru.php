#!/usr/bin/env php
<?php

use conversion\pass_thru;

require __DIR__ . '/../lib.php';
require __DIR__ . '/../classes/conversion/pass_thru.php';

perform_conversion(pass_thru::class);
