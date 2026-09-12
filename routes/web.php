<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $serverTime = now()->format('Y-m-d H:i:s T');

    $endpoints = [
        ['POST', '/api/v1/auth/register', 'Create a new user account'],
        ['POST', '/api/v1/auth/login', 'Authenticate and receive an API token'],
        ['GET', '/api/v1/plans', 'List available subscription plans'],
        ['GET', '/api/v1/dashboard', 'Authenticated dashboard summary'],
        ['GET', '/api/v1/hardware-prices', 'List hardware prices'],
        ['GET', '/api/v1/hardware-prices/statistics', 'Hardware price statistics'],
        ['GET', '/api/v1/projects', 'List projects'],
    ];

    $endpointRows = '';
    foreach ($endpoints as [$method, $path, $description]) {
        $endpointRows .= '<tr>'
            . '<td><span class="method ' . strtolower($method) . '">' . $method . '</span></td>'
            . '<td><code>' . $path . '</code></td>'
            . '<td>' . $description . '</td>'
            . '</tr>';
    }

    return response(<<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>BOQ System API — Status</title>
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
                background: #0f172a;
                color: #e2e8f0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }
            .card {
                background: #1e293b;
                border: 1px solid #334155;
                border-radius: 16px;
                padding: 40px;
                max-width: 720px;
                width: 100%;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.35);
            }
            .header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
            h1 { font-size: 24px; font-weight: 700; color: #f8fafc; }
            .badge {
                display: inline-flex; align-items: center; gap: 8px;
                background: #052e16; color: #4ade80;
                border: 1px solid #166534; border-radius: 999px;
                padding: 6px 14px; font-size: 13px; font-weight: 600;
            }
            .badge .dot { width: 8px; height: 8px; border-radius: 50%; background: #22c55e; }
            .base-url { margin-top: 8px; font-size: 14px; color: #94a3b8; }
            .base-url code { color: #7dd3fc; background: #0f172a; padding: 2px 8px; border-radius: 6px; }
            h2 { margin: 32px 0 12px; font-size: 14px; text-transform: uppercase; letter-spacing: 0.08em; color: #94a3b8; }
            table { width: 100%; border-collapse: collapse; }
            th { text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #64748b; padding: 8px 10px; border-bottom: 1px solid #334155; }
            td { padding: 10px; border-bottom: 1px solid #1e293b; font-size: 14px; vertical-align: top; }
            tr:last-child td { border-bottom: none; }
            .method {
                display: inline-block; font-size: 11px; font-weight: 700;
                padding: 3px 8px; border-radius: 6px; letter-spacing: 0.04em;
            }
            .method.get { background: #0c4a6e; color: #38bdf8; }
            .method.post { background: #14532d; color: #4ade80; }
            code { font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; font-size: 13px; color: #e2e8f0; }
            .note { margin-top: 32px; padding: 14px 16px; background: #0f172a; border: 1px solid #334155; border-radius: 10px; font-size: 13px; color: #94a3b8; }
            .server-time { margin-top: 16px; font-size: 13px; color: #64748b; }
            .server-time strong { color: #cbd5e1; font-weight: 600; }
        </style>
    </head>
    <body>
        <div class="card">
            <div class="header">
                <h1>BOQ System API</h1>
                <span class="badge"><span class="dot"></span>Online</span>
            </div>
            <p class="base-url">Base URL: <code>/api/v1</code></p>

            <h2>Key Endpoints</h2>
            <table>
                <thead>
                    <tr><th>Method</th><th>Path</th><th>Description</th></tr>
                </thead>
                <tbody>
                    {$endpointRows}
                </tbody>
            </table>

            <div class="note">The BOQ System mobile app is the primary client for this API.</div>
            <p class="server-time">Server time: <strong>{$serverTime}</strong></p>
        </div>
    </body>
    </html>
    HTML, 200, ['Content-Type' => 'text/html']);
});