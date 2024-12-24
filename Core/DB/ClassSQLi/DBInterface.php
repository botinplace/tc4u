<?php
//namespace ClassSQLi;

interface DBInterface
{
    // название таблицы с которой будем работать
    public function table(string $table);

    // возвращает все строки
    public function getAll(int $limit);

    // возвращает строку таблицы по ее id - первичный ключ
    public function get($id);

    // вставляет значение в таблицу
    public function insert($data);

    // обновляет
    public function update($id, $data);

    // удаляет
    public function delete($id);
}