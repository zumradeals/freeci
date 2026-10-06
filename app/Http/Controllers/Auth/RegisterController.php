<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Modules\Accounts\Actions\RegisterUser;
use App\Modules\Accounts\Security\EmailVerification;
use App\Modules\Notifications\Support\MailStatus;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisterController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request, RegisterUser $register): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:254', 'unique:users,email'],
            'password' => ['required', 'string', 'confirmed', Password::min(10)->letters()->numbers()],
        ]);

        $user = $register($data['name'], $data['email'], $data['password']);
        event(new Registered($user));
        if (MailStatus::configured()) {
            app(EmailVerification::class)->send($user);          // sans courrier réel : rien n'est envoyé, l'adresse reste non vérifiée
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.dashboard');
    }
}
