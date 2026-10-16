<?php
namespace App\Models;

use Pika\Model;

/** Example model. Passwords are changed with set_user_password(), never through mass assignment. */
class User extends Model
{
    protected static string $table = 'users';
    protected static array $fillable = ['username', 'email', 'status'];
}
