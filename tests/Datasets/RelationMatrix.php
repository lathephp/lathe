<?php

/*
 * The relation matrix: the six operations over the ten relation kinds. A key names its
 * case, like "morphToMany / attach", so a failing case names itself.
 */
dataset('relation matrix', function () {
    $kinds = ['belongsTo', 'hasOne', 'hasMany', 'belongsToMany', 'morphMany', 'morphOne', 'morphToMany', 'morphedByMany', 'hasOneThrough', 'hasManyThrough'];
    $operations = ['create', 'edit', 'attach', 'detach', 'validation', 'policy'];

    foreach ($kinds as $kind) {
        foreach ($operations as $operation) {
            yield "{$kind} / {$operation}" => [$kind, $operation];
        }
    }
});
