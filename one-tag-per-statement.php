#!/usr/bin/env php
<?php

use conversion\one_tag_per_statement;

require __DIR__ . '/lib.php';
require __DIR__ . '/classes/conversion/one_tag_per_statement.php';

perform_conversion(one_tag_per_statement::class);
