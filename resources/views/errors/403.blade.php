@extends('errors.layout')

@section('code', '403')
@section('title', 'You do not have access to this page')
@section('message', $exception->getMessage() && $exception->getMessage() !== 'Forbidden' ? $exception->getMessage() : 'Your account is not allowed to open this page.')
