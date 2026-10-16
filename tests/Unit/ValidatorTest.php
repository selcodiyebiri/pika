<?php
use PHPUnit\Framework\TestCase;
use Pika\Validator;

class ValidatorTest extends TestCase
{
    public function testPassesValidData(): void
    {
        $v = Validator::make(
            ['email' => 'a@b.co', 'password' => 'longenough', 'password_confirmation' => 'longenough', 'role' => 'user'],
            ['email' => 'required|email', 'password' => 'required|min:8|confirmed', 'role' => 'in:admin,user']
        );
        $this->assertFalse($v->fails());
        $this->assertSame(['email', 'password', 'role'], array_keys($v->validated()));
    }

    public function testCollectsErrors(): void
    {
        $v = Validator::make(
            ['email' => 'nope', 'password' => 'short', 'password_confirmation' => 'x'],
            ['email' => 'required|email', 'password' => 'min:8|confirmed', 'name' => 'required'],
            ['name' => 'Name']
        );
        $this->assertTrue($v->fails());
        $this->assertSame('Name is required.', $v->first('name'));
        $this->assertCount(2, $v->errors()['password']);
        $this->assertArrayHasKey('email', $v->errors());
    }

    public function testOptionalEmptyFieldSkipsOtherRules(): void
    {
        $this->assertFalse(Validator::make(['email' => ''], ['email' => 'email'])->fails());
    }

    public function testUnknownRuleThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['a' => 'x'], ['a' => 'bogus']);
    }

    public function testUniqueRuleRejectsUnsafeIdentifiers(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Validator::make(['a' => 'x'], ['a' => 'unique:users;DROP,name']);
    }
}
