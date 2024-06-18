<?php

function sanitizeInput($input)
{
    // Only apply strip_tags if $input is not null.
    return is_null($input) ? $input : strip_tags($input);
}
