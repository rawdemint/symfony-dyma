<?php

namespace App\Controller;

use App\Entity\Question;
use App\Repository\QuestionRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class HomeController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(QuestionRepository $questionRepo, ): Response
    {
        
        $questions= $questionRepo->findBy([], ['createdAt' => 'DESC']);

        // $questions = [
        //     [
        //         'id' => '1',
        //         'title' => 'Je suis une super question',
        //         'content' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Tempora, adipisci. Libero aperiam dolores excepturi, quidem maxime accusantium inventore. Illum, odio dolores! Ullam omnis veritatis laborum, animi inventore nostrum optio voluptates.',
        //         'rating' => 20,
        //         'author' => [
        //             'name' => 'Jean Dupont',
        //             'avatar' => 'https://randomuser.me/api/portraits/men/52.jpg'
        //         ],
        //         'nbrOfResponse' => 15
        //     ],
        //     [
        //         'id' => '2',
        //         'title' => 'Je suis une super question',
        //         'content' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Tempora, adipisci. Libero aperiam dolores excepturi, quidem maxime accusantium inventore. Illum, odio dolores! Ullam omnis veritatis laborum, animi inventore nostrum optio voluptates.',
        //         'rating' => 0,
        //         'author' => [
        //             'name' => 'Julie Dupont',
        //             'avatar' => 'https://randomuser.me/api/portraits/women/44.jpg'
        //         ],
        //         'nbrOfResponse' => 15
        //     ],
        //     [
        //         'id' => '3',
        //         'title' => 'Je suis une super question',
        //         'content' => 'Lorem ipsum dolor sit amet consectetur adipisicing elit. Tempora, adipisci. Libero aperiam dolores excepturi, quidem maxime accusantium inventore. Illum, odio dolores! Ullam omnis veritatis laborum, animi inventore nostrum optio voluptates.',
        //         'rating' => -15,
        //         'author' => [
        //             'name' => 'Jean Dupont',
        //             'avatar' => 'https://randomuser.me/api/portraits/men/46.jpg'
        //         ],
        //         'nbrOfResponse' => 15
        //     ],
        // ];

        return $this->render('home/index.html.twig', [
            'questions' => $questions
        ]);
    }
}
