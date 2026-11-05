<?php

function csrf_token(): string
{
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }

    return $_SESSION["csrf_token"];
}

function csrf_token_is_valid($submittedToken): bool
{
    return is_string($submittedToken)
        && isset($_SESSION["csrf_token"])
        && hash_equals($_SESSION["csrf_token"], $submittedToken);
}
