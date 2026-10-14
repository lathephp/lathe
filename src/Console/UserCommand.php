<?php

namespace Lathe\Console;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Lathe\Lathe;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

class UserCommand extends Command
{
    protected $signature = 'lathe:user
        {--name= : The user\'s name, skipping the prompt}
        {--email= : The user\'s email address, skipping the prompt}
        {--password= : The user\'s password, skipping the prompt}';

    protected $description = 'Create a user who can sign in to the panel';

    public function handle(): int
    {
        $model = $this->userModel();
        $rules = [
            'name' => 'required|min:2',
            'email' => 'required|email|unique:'.$model->getConnectionName().'.'.$model->getTable().',email',
            'password' => 'required|min:8',
        ];

        $name = $this->optionOrPrompt('name', fn () => text(label: Lathe::__('Name'), required: true, validate: ['name' => $rules['name']]));
        $email = $this->optionOrPrompt('email', fn () => text(label: Lathe::__('Email address'), required: true, validate: ['email' => $rules['email']]));
        $password = $this->optionOrPrompt('password', fn () => password(label: Lathe::__('Password'), required: true, validate: ['password' => $rules['password']]));

        $missing = array_keys(array_filter(['name' => $name, 'email' => $email, 'password' => $password], fn (?string $value) => $value === null));

        if (! $this->input->isInteractive() && $missing !== []) {
            $this->components->error(Lathe::__('Missing option: :options.', ['options' => implode(', ', array_map(fn (string $option) => "--{$option}", $missing))]));

            return self::FAILURE;
        }

        // Values that came from options skipped the prompts' rules, so the whole set is checked here.
        $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password], $rules);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->components->error($error);
            }

            return self::FAILURE;
        }

        $user = $model->newInstance()->forceFill([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make((string) $password),
        ]);
        $user->save();

        $this->components->info(Lathe::__('User created.'));

        if ($user instanceof Authenticatable && ! Gate::forUser($user)->allows('viewLathe')) {
            $this->components->warn(Lathe::__('The viewLathe gate does not admit this user yet. Add the email to gate() in app/Providers/LatheServiceProvider.php:'));
            $this->line("    '{$email}',");
        }

        return self::SUCCESS;
    }

    protected function optionOrPrompt(string $option, Closure $prompt): ?string
    {
        $value = $this->option($option);

        if (is_string($value)) {
            return $value;
        }

        $answer = $this->input->isInteractive() ? $prompt() : null;

        return is_string($answer) ? $answer : null;
    }

    protected function userModel(): Model
    {
        $class = config('auth.providers.users.model', 'App\\Models\\User');
        $model = is_string($class) && class_exists($class) ? new $class : null;

        return $model instanceof Model ? $model : throw new \RuntimeException('auth.providers.users.model is not an Eloquent model.');
    }
}
