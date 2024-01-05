<?php

namespace App\Controller;

use App\Entity\Vote;
use App\Entity\Comment;
use App\Entity\Question;
use App\Form\CommentType;
use App\Form\QuestionType;
use App\Repository\QuestionRepository;
use App\Repository\VoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class QuestionController extends AbstractController
{
    #[Route('/question/ask', name: 'question_form')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $question = new Question();
        
        $formQuestion = $this->createForm(QuestionType::class, $question);
        $formQuestion->handleRequest($request);

        if ($formQuestion->isSubmitted() && $formQuestion->isValid()) {
            $question->setNbrOfResponse(0);
            $question->setrating(0);
            $question->setAuthor($user);
            $question->setCreatedAt(new \DateTimeImmutable());
            $em->persist($question);
            $em->flush();
            $this->addFlash('success', 'Votre question a été ajouté.');

            return $this->redirectToRoute('home');
        }

        return $this->render('question/index.html.twig', [
            'form' => $formQuestion->createView(),
        ]);
    }

    #[Route('/question/{id}', name: 'question_show')]
    public function show( Request $request, Question $question, EntityManagerInterface $em): Response
    {

        $user = $this->getUser();

        $options = [
            'question' => $question,
        ];

        if ($user){
            $comment = new Comment();
            $commentForm = $this->createForm(CommentType::class, $comment);
            $commentForm->handleRequest($request);

            if ($commentForm->isSubmitted() && $commentForm->isValid()) {
                $comment->setCreatedAt(new \DateTimeImmutable());
                $comment->setRating(0);
                $comment->setQuestion($question);
                $comment->setAuthor($user);
                $question->setNbrOfResponse($question->getNbrOfResponse()+1);

                $em->persist($comment);
                $em->flush();
                $this->addFlash('success', 'Votre reponse a bien été ajouté.');
                return $this->redirect($request->getUri());
            }
            
            $options['form'] = $commentForm->createView();
        }

        return $this->render('question/show.html.twig', $options);
    }


    #[Route('/question/search/{search}', name:'question_search')]
    public function questionSearch (string $search, QuestionRepository $questionRepository, Request $request, EntityManagerInterface $em)
    {
        $questions = $questionRepository->findBySearch($search);
        return $this->json(json_encode($questions));
    }

    #[Route('/question/rating{id}/{score}', name: 'question_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function ratingQuestion (Request $request, Question $question, int $score, VoteRepository $voteRepo, EntityManagerInterface $em  )
    {

        $user = $this->getUser();
        if ($user !== $question->getAuthor()){ // si l'utilisateur est different de l'auteur

            $vote = $voteRepo->findOneBy([
                'author' => $user,
                'question' => $question
            ]);

            if ($vote){ // si il y a un vote et
                if (($vote->getIsliked() && $score > 0 ) || (!$vote->getIsLiked() && $score < 0)) { // si getIsLiked est true et que le score est > 0 ou l'inverse
                    $em->remove($vote); // on efface le score
                    $question->setRating($question->getRating() + ($score > 0 ? -1 : 1));
                }else{
                    $vote->setIsLiked(!$vote->getIsLiked());
                    $question->setRating($question->getRating() + ($score > 0 ? 2 : -2));
                }
            }else{
                $vote = new Vote();
                $vote->setAuthor($user);
                $vote->setQuestion($question);
                $vote->setIsLiked($score > 0 ? true : false);
                $question->setRating($question->getRating() + $score);
                $em->persist($vote);  
            }
            $em->flush();
        }
         // $this->addFlash('success', 'Votre vote a bien été enregistré.');
         $referer = $request->server->get('HTTP_REFERER');
         return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');
    }


    #[Route('/comment/rating{id}/{score}', name: 'comment_rating')]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function ratingComment (Request $request, Comment $comment, int $score, VoteRepository $voteRepo, EntityManagerInterface $em  )
    {
    
            $user = $this->getUser();
            if ($user !== $comment->getAuthor()){ // si l'utilisateur est different de l'auteur
    
                $vote = $voteRepo->findOneBy([
                    'author' => $user,
                    'comment' => $comment
                ]);
    
                if ($vote){ // si il y a un vote et
                    if (($vote->getIsliked() && $score > 0 ) || (!$vote->getIsLiked() && $score < 0)) { // si getIsLiked est true et que le score est > 0 ou l'inverse
                        $em->remove($vote); // on efface le score
                        $comment->setRating($comment->getRating() + ($score > 0 ? -1 : 1));
                    }else{
                        $vote->setIsLiked(!$vote->getIsLiked());
                        $comment->setRating($comment->getRating() + ($score > 0 ? 2 : -2));
                    }
                }else{
                    $vote = new Vote();
                    $vote->setAuthor($user);
                    $vote->setComment($comment);
                    $vote->setIsLiked($score > 0 ? true : false);
                    $comment->setRating($comment->getRating() + $score);
                    $em->persist($vote);  
                }
                $em->flush();
            }
    
            // $this->addFlash('success', 'Votre vote a bien été enregistré.');
            $referer = $request->server->get('HTTP_REFERER');
            return $referer ? $this->redirect($referer) : $this->redirectToRoute('home');

        }
        
       
    }


