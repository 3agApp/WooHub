@extends('errors.layout')

@section('code', '403')
@section('title', 'Access denied')
@section('message', 'You are authenticated but do not have permission to view this page.')