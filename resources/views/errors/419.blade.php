@extends('errors.layout')

@section('code', '419')
@section('title', 'This page has expired')
@section('message', 'For your security, forms expire after a period of inactivity. Go back, refresh the page and send the form again.')
@section('link', url()->previous())
@section('link_text', 'Go back')
