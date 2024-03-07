<?php

trait ValidateSubmisionMandatoryFields
{
    private const REQUIRED_FIELDS = [
        'authors' => [
            [
                'lastName',
                'firstName',
                'affiliation',
                'institutions' => ['name', 'ror']
            ]
        ],
        'article' => [
            'title',
            'doi',
            'type',
            'vor' => ['publication', 'license']
        ],
        'journal' => ['name', 'id']
    ];

    public function validateSubmissionHasMandatoryData(): array
    {
        $data = $this->getMessageDataFields();

        $missingFields = [];

        foreach (self::REQUIRED_FIELDS as $section => $fields) {
            foreach ($fields as $key => $field) {
                if (is_array($field)) {
                    foreach ($field as $nestedField => $subFields) {
                        if (is_array($subFields)) {
                            foreach ($subFields as $subField) {
                                if (!isset($data[$section][$key][$nestedField][0][$subField])) {
                                    $missingFields[] = "Missing '$subField' in the '$nestedField' sub-section of the '$key' sub-section of the '$section' section.";
                                }
                            }
                        } else {
                            if (!isset($data[$section][$key][$subFields])) {
                                $missingFields[] = "Missing '$subFields' in the '$key' sub-section of the '$section' section.";
                            }
                        }
                    }
                } else {
                    if (!isset($data[$section]) || !isset($data[$section][$field])) {
                        $missingFields[] = "Missing '$field' in the '$section' section.";
                    }
                }
            }
        }

        return $missingFields;
    }

    private function getMessageDataFields()
    {
        $data['authors'] = $this->getAuthorsData();
        $data['article'] = $this->getArticleData();
        $data['journal'] = $this->getJournalData();

        return $data;
    }
}
