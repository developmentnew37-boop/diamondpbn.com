<p>Hello Admin,</p>

<p>You requested a password reset. Click the button below:</p>

<p>
    <a href="{{ route('admin.reset.form', $token) }}"
       style="display:inline-block;
              padding:10px 18px;
              background:#ec5629;
              color:white;
              text-decoration:none;
              border-radius:6px;
              font-weight:bold;">
        Reset Password
    </a>
</p>

<p>If you did not make this request, simply ignore this email.</p>

<p>Thanks,<br>{{ config('app.name') }}</p>
