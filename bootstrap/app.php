<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request): string {
            if ($request->is('perusahaan*')) {
                return route('company.login');
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'pemerintah/api/pemeriksaan-perusahaan', 'pemerintah/api/pemeriksaan-perusahaan/*', 'pemerintah/api/status-verifikasi-perusahaan', 'pemerintah/api/status-verifikasi-perusahaan/*', 'pemerintah/api/pemeriksaan-lowongan', 'pemerintah/api/pemeriksaan-lowongan/*', 'pemerintah/api/monitoring-lowongan', 'pemerintah/api/monitoring-lowongan/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response) {
            $request = request();
            $status = $response->getStatusCode();

            if (($request->is('pemerintah/api/pemeriksaan-perusahaan', 'pemerintah/api/pemeriksaan-perusahaan/*', 'pemerintah/api/status-verifikasi-perusahaan', 'pemerintah/api/status-verifikasi-perusahaan/*', 'pemerintah/api/pemeriksaan-lowongan', 'pemerintah/api/pemeriksaan-lowongan/*', 'pemerintah/api/monitoring-lowongan', 'pemerintah/api/monitoring-lowongan/*')
                    || ($request->is('pemerintah/pemeriksaan-perusahaan/*/hasil') && $request->expectsJson()))
                && in_array($status, [401, 403, 404, 409, 422], true)) {
                $body = json_decode($response->getContent(), true);

                return response()->json([
                    'message' => match ($status) {
                        401 => 'Autentikasi diperlukan.',
                        403 => 'Akses hanya untuk Admin Pemerintah aktif.',
                        404 => 'Data tidak ditemukan.',
                        409 => 'Data berubah atau keputusan final sudah tersimpan. Muat ulang dan periksa kembali.',
                        422 => 'Parameter permintaan tidak valid.',
                    },
                    'errors' => $status === 422 ? (object) ($body['errors'] ?? []) : (object) [],
                ], $status, ['Cache-Control' => 'no-store, private']);
            }

            return $response;
        });
    })->create();
