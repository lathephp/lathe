<?php

// A composer require run inside an agent worktree locks a path package at the worktree's
// branch name (dev-worktree-agent-...), and composer on main then refuses to install.
it('locks no package at an agent worktree branch', function () {
    $lock = (string) file_get_contents(dirname(__DIR__, 3).'/composer.lock');

    expect($lock)->not->toContain('dev-worktree-');
});
