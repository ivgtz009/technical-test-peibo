# Orders API — Technical Test

## Requisitos
Docker Desktop + Docker Compose

## Inicio rápido

docker compose up -d --build
# esperar 20s a que MySQL arranque
curl http://localhost:8080/health

La API queda disponible en http://localhost:8080


# TEST

# Tests unitarios
docker compose exec php vendor/bin/phpunit --testsuite Unit

# Tests de integración con MySQL
docker compose exec php vendor/bin/phpunit --testsuite Integration

# TESTS completos
docker compose exec php composer test


# PHPStan y PHP-CS-Fixer
docker compose exec php composer stan   # PHPStan nivel 6
docker compose exec php composer lint   # PHP-CS-Fixer PSR-12


# ENDPOINTS
Método	Ruta	                Body	                                        Respuesta exitosa
POST	/products	            {name, price, stock}	                        201 {data:{id}}
GET	    /products	            —	                                            200 {data:[...]}
POST	/orders	                {customer_id, items:[{product_id, quantity}]}	201 {data:{id}}
GET	    /orders	                status, customer_id, page, limit	            200 {data:[...], meta:{page,limit,total}}
GET	    /orders/{id}	        —	                                            200 {data:{...}}
PATCH	/orders/{id}/confirm	—	                                            200 {data:{status:"confirmed"}}
PATCH	/orders/{id}/cancel	    —	                                            200 {data:{status:"cancelled"}}

Respuestas con Error
{"error": "mensaje", "errors": {"campo": "razon"}} con status 404, 409, 422 o 500.


# Representacion del dinero
Elegí enteros en decimales en lugar de float o strings con bcmath por las siguientes razones: así evito errores de precisión, no quise tener dependencia de bcmath para sumas y multiplicaciones por enteros.


# Concurrencia
Para resolver el conflicto de que dos request intenten confirmar la misama orden al mismo tiempo implementé lo siguiente:
"SELECT ... FOR UPDATE en ProductRepository::findByIds()" bloquea las filas de productos durante la transaccion. La segunda confirmacion espera a que la primera haga commit, luego relee el stock actualizado y falla la validación.
"UPDATE orders SET status = 'confirmed' WHERE id = ? AND status = 'pending'" es una actualización condicional. Solo una request afecta la fila, la otra ve affected_rows = 0 y recibe 409 Conflict.
Ambos corren dentro del mismo bloque begin_transaction() / commit() / rollback() en OrderService::confirm(), así que un falla, en cualquier punto deja el stock y el estado de la orden intactos.

Descarté el lock a nivel de aplicación porque no quería agregar infraestructura extra y aparte la base de datos ya garantiza el invariante con los tipos de datos y validaciones que usé.

# Estructura del proyecto
Capa	            Ruta	                Responsabilidad
Dominio	            src/Domain	            Enum OrderStatus, VO Money, OrderCalculator con test unitario.
Aplicación	        src/Application	        OrderService: transacciones, orquestación, reglas de estado.
Persistencia	    src/Repository	        Prepared statements, FOR UPDATE, actualizaciones condicionales.
HTTP	            src/Http	            Router, Request, Response, Controllers.
Infraestructura	    src/Infrastructure	    Database, conexión mysqli.

Las dependencias se inyectan por constructor en public/index.php


# Code Review

El problema más grave es la inyección SQL en $_GET['id']: un atacante puede leer o borrar toda la base de datos, afectando a todos los usuarios del sistema. Segundo, if ($order == false) no hace return: si la orden no existe, el código sigue, $order['items'] falla y el usuario recibe un 500 o un JSON que no entiende. Tercero, echo "not found" con status 200 impide al cliente distinguir éxito de fallo, rompiendo la integracion. Cuarto, recalcular $order['total'] sumando items ignora el total guardado: si hay descuentos o impuestos, el cobro es incorrecto. Quinto, mysqli_query sin prepared statements expone inyección y silencia errores. Sexto, la ausencia de declare(strict_types=1) y de tipos hace que $id sea string y las comparaciones laxas, con comportamiento impredecible. Séptimo, mezclar HTTP, SQL y presentación en una función la hace no apto para aplicar tests. Octavo, header() después de posible output genera warnings.

Reestructuraría en capas: un OrderRepository con prepared statements que devuelve un array tipado, un OrderService con la lógica, un Response que serialice JSON y un front controller con un set_exception_handler que mapee excepciones a status HTTP en un solo sitio. Así cada capa es testeable y el manejo de errores es consistente.


# Short Written section

Para la confirmación de órdenes analicé dos opciones: validar stock con un SELECT normal y luego decrementar, o bloquear las filas con SELECT ... FOR UPDATE dentro de la transacción. No elegí lo primero porque tiene una condición de carrera: dos confirmaciones concurrentes pueden leer el mismo stock, pasar la validación y decrementar, dejando stock negativo. En cambio, con FOR UPDATE, la segunda transacción espera a que la primera haga commit y luego ve el stock actualizado, fallando la validación. Además añadí un segundo mecanismo: UPDATE orders SET status='confirmed' WHERE id=? AND status='pending', que garantiza que solo una request cambie el estado; la otra ve affected_rows=0 y recibe 409


# Notas Adicionales
Estos 3 puntos los tuve que aprender sobre la marcha ya que son temas que no domino
Montar el proyecto con Docker: solo había creado un container para montar una base de datos en PostgreSQL, pero para montar el proyecto completo si tuve que invertir tiempo en investigar poder aplicarlo.

Aplicar Unit Test: Solo había hecho unit test en front para Angular con Jasmine, pero en back no había hecho test, también deciqué tiempo a investigarlo, incluso consideré descartarlo pero creo que era parte importante de la prueba.

PSR-4 y Estructura de carpetas del proyecto con PHP puro: Nunca había creado un proyecto con PHP puro, solo había ocupado Framworks como Zend y Laravel. Pero dedicar tiempo para investigar cuál sería una buena arquitectura del proyecto y aplicar PSR-4 me sirvió para comprender que hacen los frameworks por detrás.

**Aclaro que sí usé la IA a pesar de que el Test menciona que no se permitía, la usé para investigar estos puntos específicos que no domino, lo ví necesario para acelerar la curva de de aprendizaje y no me quitara tiempo para hacer lo más importante para mi, que era la lógica del proyecto y su funcionalidad, también aclaro que no copié y pegué lo que me pasó la IA, de hecho descarté varias opciones que me proponía cómo algunas carpetas extra que yo consideré innecesarias para el mínimo entregable de esta prueba, también busqué ejemplos de los archivos composer.json, docker-compose.yml y Dockerfile**


# Conclusion
Me encantó hacer esta prueba técnica, aprendí y reforcé varios temas respecto a PHP, agradezco la oportunidad