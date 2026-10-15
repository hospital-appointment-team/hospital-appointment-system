<?php
declare(strict_types=1);
namespace Hospital\Models;

final class Appointment extends Model
{
    public const STATUSES = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No-Show'];
    public const SELECT = 'SELECT a.*,p.name AS patient_name,u.name AS doctor_name,s.name AS specialization FROM appointments a JOIN users p ON p.id=a.patient_id JOIN doctors d ON d.id=a.doctor_id JOIN users u ON u.id=d.user_id JOIN specializations s ON s.id=d.specialization_id';
}
