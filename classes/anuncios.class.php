<?php
    class Anuncios{

        public function getAnuncio($id){
            global $pdo;
            $array = array();

            $sql = $pdo->prepare("SELECT *,
            (select categorias.nome from categorias where categorias.id = anuncios.id_categoria) 
            as categoria,
            (select usuarios.telefone from usuarios where usuarios.id = anuncios.id_usuario)
            as telefone 
            FROM anuncios WHERE id = :id");
            $sql->bindValue(":id", $id);
            $sql->execute();

            if($sql->rowCount() > 0){
                $array = $sql->fetch();
                $array['fotos'] = array();

                $sql = $pdo->prepare("SELECT id, url FROM anuncios_imagens WHERE id_anuncio = :id_anuncio");
                $sql->bindValue(":id_anuncio", $id);
                $sql->execute();

                if($sql->rowCount() > 0){
                    $array['fotos'] = $sql->fetchAll();
                }
            }

            return $array;
        }

        public function getMeusAnuncios(){
            global $pdo;
            $array = array();
            $sql = $pdo->prepare("SELECT *,
                (select anuncios_imagens.url from anuncios_imagens where anuncios_imagens.id_anuncio = anuncios.id limit 1) 
                as url
                FROM anuncios
                WHERE id_usuario = :id_usuario"
            );
            $sql->bindValue(":id_usuario", $_SESSION['login']);
            $sql->execute();

            if($sql->rowCount() > 0){
                $array = $sql->fetchAll();
            }
            return $array;
        }

        public function getQtdAnuncios($filtros){
            global $pdo;

            $filtroString = array('1=1');

            if(!empty($filtros['categoria'])){
                $filtroString[] = 'anuncios.id_categoria = :id_categoria';
            }
            if(!empty($filtros['preco'])){
                $filtroString[] = 'anuncios.valor BETWEEN :preco1 AND :preco2';
            }
            if(!empty($filtros['estado'])){
                $filtroString[] = 'anuncios.estado = :estado';
            }

            $sql = $pdo->prepare ("SELECT COUNT(*) as quantidade FROM anuncios
                    WHERE ".implode(' AND ', $filtroString));

            if(!empty($filtros['categoria'])){
                $sql->bindValue(":id_categoria", $filtros['categoria']);
            }
            if(!empty($filtros['preco'])){
                $preco = explode('-', $filtros['preco']);
                $sql->bindValue(":preco1", $preco[0]);
                $sql->bindValue(":preco2", $preco[1]);
            }
            if(!empty($filtros['estado'])){
                $sql->bindValue(":estado", $filtros['estado']);
            }

            $sql->execute();
            $row = $sql->fetch();

            return $row['quantidade'];
        }

        public function getUltimosAnuncios($page, $qtdPaginas, $filtros){
            global $pdo;

            $offset = ($page - 1) * $qtdPaginas;

            $array = array();

            $filtroString = array('1=1');
            if(!empty($filtros['categoria'])){
                $filtroString[] = 'anuncios.id_categoria = :id_categoria';
            }
            if(!empty($filtros['preco'])){
                $filtroString[] = 'anuncios.valor BETWEEN :preco1 AND :preco2';
            }
            if(!empty($filtros['estado'])){
                $filtroString[] = 'anuncios.estado = :estado';
            }

            $sql = $pdo->prepare("SELECT *,
                (select anuncios_imagens.url from anuncios_imagens where anuncios_imagens.id_anuncio = anuncios.id limit 1) 
                as url,
                (select categorias.nome from categorias where anuncios.id_categoria = categorias.id)
                as categoria
                FROM anuncios WHERE 
                ".implode(' AND ', $filtroString)."
                ORDER BY id DESC LIMIT $offset, $qtdPaginas");

            if(!empty($filtros['categoria'])){
                $sql->bindValue(":id_categoria", $filtros['categoria']);
            }
            if(!empty($filtros['preco'])){
                $preco = explode('-', $filtros['preco']);
                $sql->bindValue(":preco1", $preco[0]);
                $sql->bindValue(":preco2", $preco[1]);
            }
            if(!empty($filtros['estado'])){
                $sql->bindValue(":estado", $filtros['estado']);
            }

            $sql->execute();

            if($sql->rowCount() > 0){
                $array = $sql->fetchAll();
            }

            return $array;
        }

        public function adicionarAnuncio($titulo, $categoria, $valor, $descricao, $estado){
            global $pdo;
            $sql = $pdo->prepare("INSERT INTO anuncios SET titulo = :titulo, id_categoria = :id_categoria, 
                                            id_usuario = :id_usuario, valor = :valor, descricao = :descricao, estado = :estado");
            $sql->bindValue(":titulo", $titulo);
            $sql->bindValue(":id_categoria", $categoria);
            $sql->bindValue(":id_usuario", $_SESSION['login']);
            $sql->bindValue(":valor", $valor);
            $sql->bindValue(":descricao", $descricao);
            $sql->bindValue(":estado", $estado);
            $sql->execute();
            return true;
        }

        public function alterarAnuncio($titulo, $categoria, $valor, $descricao, $estado, $fotos, $id){
            global $pdo;
            $sql = $pdo->prepare("UPDATE anuncios SET titulo = :titulo, id_categoria = :id_categoria, 
                                            id_usuario = :id_usuario, valor = :valor, descricao = :descricao, estado = :estado WHERE id = :id");
            $sql->bindValue(":titulo", $titulo);
            $sql->bindValue(":id_categoria", $categoria);
            $sql->bindValue(":id_usuario", $_SESSION['login']);
            $sql->bindValue(":valor", $valor);
            $sql->bindValue(":descricao", $descricao);
            $sql->bindValue(":estado", $estado);
            $sql->bindValue(":id", $id);
            $sql->execute();

            if(count($fotos) > 0){
                for($i=0;$i<count($fotos['tmp_name']);$i++){
                    $tipo = $fotos['type'][$i];
                    if(in_array($tipo, array('image/jpeg', 'image/png'))){
                        $tmpname = md5(time().rand(0,9999)).'.jpg';
                        move_uploaded_file($fotos['tmp_name'][$i], 'assets/images/anuncios/'.$tmpname);

                        list($width_orig, $height_orig) = getimagesize('assets/images/anuncios/'.$tmpname);
                        $ratio = $width_orig/$height_orig;

                        $width = 500;
                        $height = 500;

                        if($width/$height > $ratio){
                            $width = $height*$ratio;
                        }else{
                            $height = $width/$ratio;
                        }

                        $img = imagecreatetruecolor($width, $height);
                        if($tipo == 'image/jpeg'){
                            $origi = imagecreatefromjpeg('assets/images/anuncios/'.$tmpname);
                        }elseif($tipo == 'image/png'){
                            $origi = imagecreatefrompng('assets/images/anuncios/'.$tmpname);
                        }

                        imagecopyresampled($img, $origi, 0, 0, 0, 0, $width, $height, $width_orig, $height_orig);

                        imagejpeg($img, 'assets/images/anuncios/'.$tmpname, 80);

                        $sql = $pdo->prepare("INSERT INTO anuncios_imagens SET id_anuncio = :id_anuncio, url = :url");
                        $sql->bindValue(":id_anuncio", $id);
                        $sql->bindValue(":url", $tmpname);
                        $sql->execute();

                    }
                }
            }

            return true;

        }

        public function excluirAnuncio($id){
            global $pdo;

            $sql = $pdo->prepare("SELECT * FROM anuncios WHERE id = :id");
            $sql->bindValue(":id", $id);
            $sql->execute();
            if($sql->rowCount() > 0){
                $sql = $pdo->prepare("DELETE FROM anuncios_imagens WHERE id_anuncio = :id_anuncio");
                $sql->bindValue(":id_anuncio", $id);
                $sql->execute();

                $sql = $pdo->prepare("DELETE FROM anuncios WHERE id = :id");
                $sql->bindValue(":id", $id);
                $sql->execute();

                return true;
            }
            else{
                return false;
            }
        }

        public function excluirImagemAnuncio($id){
            global $pdo;

            $idAnuncio = 0;

            $sql = $pdo->prepare("SELECT * FROM anuncios_imagens WHERE id = :id");
            $sql->bindValue(":id", $id);
            $sql->execute();

            if($sql->rowCount() > 0){
                $row = $sql->fetch();
                $idAnuncio = $row['id_anuncio'];

                $sql = $pdo->prepare("DELETE FROM anuncios_imagens WHERE id = :id");
                $sql->bindValue(":id", $id);
                $sql->execute();

                return $idAnuncio;
            }
        }
    }
?>
