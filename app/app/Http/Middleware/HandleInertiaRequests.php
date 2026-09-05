<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'error' => fn () => $request->session()->get('error'),
                'success' => fn () => $request->session()->get('success'),
                // 失敗でも成功でもない、状態を知らせるだけの中立なメッセージ
                // (例: 進行中のトレーニングへ再誘導した、等)。ok/warn の色は
                // 意味を持つ色として予約されているため、info はニュートラルな
                // 配色(line/ink-2)で表示する。
                'info' => fn () => $request->session()->get('info'),
            ],
        ];
    }
}
