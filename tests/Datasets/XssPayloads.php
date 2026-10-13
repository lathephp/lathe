<?php

// The payloads every HTML path must neutralise, with what the sanitizer leaves of each.
dataset('xss_payloads', [
    'script tag' => ['<script>alert(1)</script>', ''],
    'img onerror' => ['<img src=x onerror=alert(1)>', '<img src="x" />'],
    'javascript link' => ['<a href="javascript:alert(1)">x</a>', '<a>x</a>'],
    'svg onload' => ['<svg onload=alert(1)>', ''],
    'iframe' => ['<iframe src="https://example.com">', ''],
    'data link' => ['<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>', '<a>x</a>'],
    'style tag' => ['<style>p{}</style>', ''],
    'style attribute' => ['<p style="color:red">x</p>', '<p>x</p>'],
]);
