<?php
namespace App\Controllers;

use Pika\Controller,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\HttpFoundation\Response;

class Home extends Controller
{
    public function main(Request $request, Response $response)
    {
        return $response->setContent($this->view('home', ['title' => 'Pika']));
    }
}
