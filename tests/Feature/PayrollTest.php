<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

use Database\Factories\EmployeeFactory;

class PayrollTest extends TestCase
{
    use DatabaseTransactions;

    private $hourlyRate = 200; // часовая ставка

    // создание сотрудника

    public function test_create_employee()
    {
        $response = $this->postJson('/api/employees', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'id', 'email', 'created_at', 'updated_at'
                 ]);

        $this->assertDatabaseHas('employees', ['email' => 'test@example.com']);
    }

    // тест на валидацию при создании сотрудника

    public function test_create_employee_validation_error()
    {
        $response = $this->postJson('/api/employees', [
            'email' => '',
            'password' => '123'
        ]);

        $response->assertStatus(422)
                 ->assertJsonStructure([
                     'errors' => [
                         'email',
                         'password'
                     ]
                 ]);
    }

    // тест на уникальность майла при создании сотрудника

    public function test_create_employee_unique_email()
    {
        $existingEmployee = Employee::factory()->create([
            'email' => 'test@example.com',
        ]);

        $response = $this->postJson('/api/employees', [
            'email' => 'test@example.com',
            'password' => 'password'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors([
                     'email' => 'The email has already been taken.'
                 ]);
    }

    // тест на добавление транзации

    public function test_add_transaction()
    {
        $employee = Employee::factory()->create();

        $hours = rand(1, 12);
        $response = $this->postJson('/api/transactions', [
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('transactions', [
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);
    }

    // тест на добавление транзакции без указания id сотрудника

    public function test_add_transaction_employee_id_required()
    {
        $response = $this->postJson('/api/transactions', [
            'hours' => 8,
            'paid' => false
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors('employee_id');
    }

    // тест на просмотр невыплаченных транзакций (200)

    public function test_get_salaries()
    {
        $employee = Employee::factory()->create();
        $hours = rand(1, 12);
        $transaction = Transaction::create([
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);


        $response = $this->getJson('/api/salaries');


        $response->assertStatus(200);

        $response->assertJson([
            [
                'employee_id' => $employee->id,
                'total' => $transaction->hours * $this->hourlyRate
            ]
        ]);

    }

    // тест на просмотр невыплаченных транзакций (422)

    public function test_get_salaries_no_transactions()
    {
        $this->assertDatabaseMissing('transactions', ['paid' => false]);

        $response = $this->getJson('/api/salaries');

        $response->assertStatus(422)
                ->assertJson([
                    'error' => 'No unpaid salaries found.'
                ]);
    }

    // тест на выплату транзакций (200)

    public function test_payout_salaries()
    {
        $employee = Employee::factory()->create();
        $hours = rand(1, 12);
        $transaction = Transaction::create([
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'employee_id' => $employee->id,
            'hours' => $hours,
            'paid' => false
        ]);

        $response = $this->getJson('/api/payout');

        $response->assertStatus(200)
             ->assertJson(['message' => 'Выплата совершена!']);
    }

    // тест на выплату транзакций (422)

    public function test_payout_salaries_missing_salaries()
    {
        $response = $this->getJson('/api/payout');

        $response->assertStatus(422)
            ->assertJson(['message' => 'Нет неоплаченных транзакций для выплаты.']);
    }
}
