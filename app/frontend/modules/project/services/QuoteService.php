<?php


namespace app\frontend\modules\project\services;

use app\frontend\modules\project\repositories\QuoteRepositoryInterface;
class QuoteService
{
    private QuoteRepositoryInterface $quoteRepository;

    public function __construct(QuoteRepositoryInterface $quoteRepository)
    {
        $this->quoteRepository = $quoteRepository;
    }


    public function saveTemplate(string $field_data):bool
    {
        return $this->quoteRepository->saveTemplate($field_data);
    }

    public function getTemplate():array
    {
        return $this->quoteRepository->getTemplate();
    }






}