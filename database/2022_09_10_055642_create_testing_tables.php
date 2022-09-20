<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Jhavenz\LaravelBatchUpdate\Tests\Fixtures\User;

return new class () extends Migration {
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->string('name');
            $table->string('email');
            $table->string('password');
        });

        Schema::create('posts', function (Blueprint $table) {
            $table->uuid('id');
            $table->timestamps();
            $table->string('title');
            $table->string('slug');
            $table->text('body');

            $table->foreignIdFor(User::class)->constrained();
        });

        Schema::create('type_circuses', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->date('date_column');
            $table->dateTime('datetime_column');
            $table->time('time_column');
            $table->timestamp('timestamp_column');
            $table->boolean('boolean_column');
            $table->double('double_column');
            $table->decimal('decimal_column', 2);
            $table->float('float_column');
            $table->json('json_column');
            $table->json('object_column');
        });
    }
};
