<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class PayrollController extends Controller
{
    private $hourlyRate = 200; // Часовая ставка

    public function createEmployee(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:employees',
            'password' => 'required|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $employee = Employee::create([
            'email' => $request->email,
            'password' => Hash::make($request->password)
        ]);

        return response()->json($employee, 201);
    }

    public function addTransaction(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees,id',
            'hours' => 'required|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }


        $transaction = new Transaction;
        $transaction->employee_id = $request->employee_id;
        $transaction->hours = $request->hours;
        $transaction->paid = false;
        $transaction->save();

        return response()->json($transaction, 201);
    }

    public function getSalaries()
    {
        $salaries = Transaction::where('paid', false)
                                ->selectRaw('employee_id, SUM(hours * ?) as total', [$this->hourlyRate])
                                ->groupBy('employee_id')
                                ->get();
                                // ->toSql();

        // dd($salaries);
        if ($salaries->count() > 0) {
            return response()->json($salaries, 200);
        } else {
            return response()->json(['error' => 'No unpaid salaries found.'], 422);
        }
    }

    public function payoutSalaries()
    {
        $transactions = Transaction::where('paid', false)->get();

        if ($transactions->count() > 0) {
            foreach ($transactions as $transaction) {
                $transaction->update(['paid' => true]);
            }
            return response()->json(['message' => 'Выплата совершена!'], 200);
        } else {
            return response()->json(['message' => 'Нет неоплаченных транзакций для выплаты.'], 422);
        }
    }
}
