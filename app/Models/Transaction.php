<?php
/**
 * Transaction.php
 * Copyright (c) 2017 thegrumpydictator@gmail.com
 *
 * This file is part of Firefly III.
 *
 * Firefly III is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Firefly III is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Firefly III. If not, see <http://www.gnu.org/licenses/>.
 */
declare(strict_types=1);

namespace FireflyIII\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class Transaction.
 *
 * @property int                 $journal_id
 * @property Carbon              $date
 * @property string              $transaction_description
 * @property string              $transaction_amount
 * @property string              $transaction_foreign_amount
 * @property string              $transaction_type_type
 * @property string              $foreign_currency_symbol
 * @property int                 $foreign_currency_dp
 * @property int                 $account_id
 * @property string              $account_name
 * @property string              $account_iban
 * @property string              $account_number
 * @property string              $account_bic
 * @property string              $account_type
 * @property string              $account_currency_code
 * @property int                 $opposing_account_id
 * @property string              $opposing_account_name
 * @property string              $opposing_account_iban
 * @property string              $opposing_account_number
 * @property string              $opposing_account_bic
 * @property string              $opposing_account_type
 * @property string              $opposing_currency_code
 * @property int                 $transaction_budget_id
 * @property string              $transaction_budget_name
 * @property int                 $transaction_journal_budget_id
 * @property string              $transaction_journal_budget_name
 * @property int                 $transaction_category_id
 * @property string              $transaction_category_name
 * @property int                 $transaction_journal_category_id
 * @property string              $transaction_journal_category_name
 * @property int                 $bill_id
 * @property string              $bill_name
 * @property string              $notes
 * @property string              $tags
 * @property string              $transaction_currency_symbol
 * @property int                 $transaction_currency_dp
 * @property string              $transaction_currency_code
 * @property string              $description
 * @property bool                $is_split
 * @property int                 $attachmentCount
 * @property int                 $transaction_currency_id
 * @property int                 $foreign_currency_id
 * @property string              $amount
 * @property string              $foreign_amount
 * @property TransactionJournal  $transactionJournal
 * @property Account             $account
 * @property int                 $identifier
 * @property int                 $id
 * @property TransactionCurrency $transactionCurrency
 * @property int                 $transaction_journal_id
 * @property TransactionCurrency $foreignCurrency
 * @property string              $before      // used in audit reports.
 * @property string              $after       // used in audit reports.
 * @property int                 $opposing_id // ID of the opposing transaction, used in collector
 * @property bool                $encrypted   // is the journal encrypted
 * @property                     $bill_name_encrypted
 */
class Transaction extends Model
{
    /**
     * The attributes that should be casted to native types.
     *
     * @var array
     */
    protected $casts
        = [
            'created_at'          => 'datetime',
            'updated_at'          => 'datetime',
            'deleted_at'          => 'datetime',
            'identifier'          => 'int',
            'encrypted'           => 'boolean', // model does not have these fields though
            'bill_name_encrypted' => 'boolean',
            'reconciled'          => 'boolean',
        ];
    /**
     * @var array
     */
    protected $fillable
        = ['account_id', 'transaction_journal_id', 'description', 'amount', 'identifier', 'transaction_currency_id', 'foreign_currency_id',
           'foreign_amount', 'reconciled'];
    /**
     * @var array
     */
    protected $hidden = ['encrypted'];

    /**
     * @codeCoverageIgnore
     *
     * @param Builder $query
     * @param string  $table
     *
     * @return bool
     */
    public static function isJoined(Builder $query, string $table): bool
    {
        $joins = $query->getQuery()->joins;
        if (null === $joins) {
            return false;
        }
        foreach ($joins as $join) {
            if ($join->table === $table) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $value
     *
     * @return Transaction
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException
     */
    public static function routeBinder(string $value): Transaction
    {
        if (auth()->check()) {
            $transactionId = (int)$value;
            $transaction   = auth()->user()->transactions()->where('transactions.id', $transactionId)
                                   ->first(['transactions.*']);
            if (null !== $transaction) {
                return $transaction;
            }
        }

        throw new NotFoundHttpException;
    }

    use SoftDeletes;

    /**
     * @codeCoverageIgnore
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * @codeCoverageIgnore
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function budgets()
    {
        return $this->belongsToMany(Budget::class);
    }

    /**
     * @codeCoverageIgnore
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function categories()
    {
        return $this->belongsToMany(Category::class);
    }

    /**
     * @codeCoverageIgnore
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function foreignCurrency()
    {
        return $this->belongsTo(TransactionCurrency::class, 'foreign_currency_id');
    }

    /**
     * @codeCoverageIgnore
     *
     * @param $value
     *
     * @return float|int
     */
    public function getAmountAttribute($value)
    {
        return $value;
    }

    /**
     * @codeCoverageIgnore
     *
     * @param Builder $query
     * @param Carbon  $date
     */
    public function scopeAfter(Builder $query, Carbon $date)
    {
        if (!self::isJoined($query, 'transaction_journals')) {
            $query->leftJoin('transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id');
        }
        $query->where('transaction_journals.date', '>=', $date->format('Y-m-d 00:00:00'));
    }

    /**
     * @codeCoverageIgnore
     *
     * @param Builder $query
     * @param Carbon  $date
     */
    public function scopeBefore(Builder $query, Carbon $date)
    {
        if (!self::isJoined($query, 'transaction_journals')) {
            $query->leftJoin('transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id');
        }
        $query->where('transaction_journals.date', '<=', $date->format('Y-m-d 23:59:59'));
    }

    /**
     * @codeCoverageIgnore
     *
     * @param Builder $query
     * @param array   $types
     */
    public function scopeTransactionTypes(Builder $query, array $types)
    {
        if (!self::isJoined($query, 'transaction_journals')) {
            $query->leftJoin('transaction_journals', 'transaction_journals.id', '=', 'transactions.transaction_journal_id');
        }

        if (!self::isJoined($query, 'transaction_types')) {
            $query->leftJoin('transaction_types', 'transaction_types.id', '=', 'transaction_journals.transaction_type_id');
        }
        $query->whereIn('transaction_types.type', $types);
    }

    /**
     * @codeCoverageIgnore
     *
     * @param $value
     */
    public function setAmountAttribute($value)
    {
        $this->attributes['amount'] = (string)$value;
    }

    /**
     * @codeCoverageIgnore
     * @return BelongsTo
     */
    public function transactionCurrency(): BelongsTo
    {
        return $this->belongsTo(TransactionCurrency::class);
    }

    /**
     * @codeCoverageIgnore
     * @return BelongsTo
     */
    public function transactionJournal(): BelongsTo
    {
        return $this->belongsTo(TransactionJournal::class);
    }
}
