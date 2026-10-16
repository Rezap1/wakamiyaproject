<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('WMS_AGENDA')) {
            return;
        }

        Schema::create('WMS_AGENDA', function (Blueprint $table): void {
            $table->string('Agenda_ID', 191)->primary();
            $table->date('Agenda_Date')->index();
            $table->time('Start_Time');
            $table->time('End_Time');
            $table->string('Agenda_Type', 32)->index();
            $table->text('Related_Parties');
            $table->text('Purpose');
            $table->string('PIC_User_ID', 191)->index();
            $table->string('Status', 32)->default('SCHEDULED')->index();
            $table->longText('Activity_Result')->nullable();
            $table->longText('Follow_Up')->nullable();
            $table->date('Follow_Up_Deadline')->nullable()->index();
            $table->string('Follow_Up_Status', 32)->default('NOT_STARTED')->index();
            $table->longText('Document_Notes')->nullable();
            $table->string('Created_By', 191);
            $table->dateTime('Created_At', 6);
            $table->string('Updated_By', 191);
            $table->dateTime('Updated_At', 6);
            $table->dateTime('Deleted_At', 6)->nullable()->index();
            $table->index(['Agenda_Date', 'Status'], 'wms_agenda_date_status_idx');
            $table->index(['PIC_User_ID', 'Follow_Up_Status'], 'wms_agenda_pic_follow_up_idx');
            $table->foreign('PIC_User_ID', 'wms_agenda_pic_fk')->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
            $table->foreign('Created_By', 'wms_agenda_creator_fk')->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
            $table->foreign('Updated_By', 'wms_agenda_updater_fk')->references('User_ID')->on('MASTER_USER')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('WMS_AGENDA');
    }
};
