<?php

test('qxlog_quotes esta incluido en los planes basic y pro', function () {
    $plans = config('billing.plans');

    expect($plans['basic']['features'])->toContain('qxlog_quotes');
    expect($plans['pro']['features'])->toContain('qxlog_quotes');
});
