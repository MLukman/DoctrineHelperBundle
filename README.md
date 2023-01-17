# Doctrine Helper Bundle

## Functionalities Provided

### 1. Consolidation of common Doctrine operations in DataStore service

Most of the Doctrine operations require chaining a few function calls, like for example:

```php
$count = $em->getRepository(Comment::class)
    ->createQueryBuilder('c')
    ->select('COUNT(1)')
    ->where('email = :email')
    ->setParameter('email', $email)
    ->getQuery()
    ->getSingleScalarResult();
```

The `DataStore` service provided by this bundle has simplified this operation into:

```php
$count = $ds->count(Comment::class, ['email' => $email]);
```

The following are operations that `DataStore` has simplified:

| #    | Operation                                                    | Pure Doctrine                                                | Using DataStore                                              |
| ---- | ------------------------------------------------------------ | ------------------------------------------------------------ | ------------------------------------------------------------ |
| 1    | Query the record with a specific ID                          | $em<br />->getRepository(Comment::class)<br />->find(3);     | $ds->queryOne(Comment::class, 3);                            |
| 2    | Query latest record of Comment with a specific email         | $em<br />->getRepository(Comment::class)<br />->findOneBy(['email' => $email], ['date'=>'DESC']); | $ds->queryOne(Comment::class, ['email' => $email], ['date'=>'DESC']); |
| 3    | Query up to 10 latest records of Comment with a specific email | $em<br />->getRepository(Comment::class)<br />->findBy(['email' => $email], ['date'=>'DESC'], 10); | $ds->queryMany(Comment::class, ['email' => $email], ['date'=>'DESC'], 10); |
| 4    | Query using OR conditions                                    | $em<br />->getRepository(Comment::class)<br/>->createQueryBuilder('c')<br />->where('e.email = :email')<br />->orWhere('e.name = :name')<br />->setParameter('email', $email)<br />->setParameter('name', $name)<br />->setMaxResults($limit)<br />->addOrderBy('e.date', 'DESC')<br />->getQuery()<br />->getResult() | $ds->queryUsingOr(Comment::class, ['email' => $email, 'name' => $name], ['created' => 'DESC'], $limit); |

### 2. Conversion of request body into objects

Common request body handling flow is as below.

```mermaid
graph LR
    client(Client) -- Raw body --> kernel(Symfony HTTP Kernel)
    kernel -- Request object --> controller(Controller)
    controller -- Entity object --> orm(Doctrine ORM)
    orm -- SQL --> db(Database)

```

Inside the controller, there would be many lines of code that manually read parameters one by one from the Request object and transfer it into the corresponding Entity object. What if the controller can just call a single line of code to do so?

```php
$specificRequest->populate($specificEntity);
```

This helper bundle has `Service\RequestBodyConverter` which does that!

First, create a PHP class that extends`DTO\RequestBody`, for example:

```php
class CommentRequest extends RequestBody
{
    public ?string $name;
    public ?string $email;
    public ?string $comment;
}
```

For simplicity, just let all its properties `public` instead of using setters/getters.

Next, modify the corresponding Entity class to implement `DTO\RequestBodyTargetInterface`. For now there is no method to implement and this interface is just for type-detection. However, the Entity class must either have public properties with the same names as the ones inside the `DTO\RequestBody` subclass or corresponding setter methods or mixture of both. Otherwisex the parameters from the request will be ignored. For example:

```php
#[ORM\Entity]
class Comment implements RequestBodyTargetInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 30)]
    public ?string $name = null;

    #[ORM\Column(length: 30)]
    public ?string $email = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    public function setComment(string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }
}
```

Finally, modify the controller method by doing:

- Add the `RequestBody` subclass to the list of the method parameters. Make it optional by prepending the class name with `?`.

  ```php
  #[Route('/comments/post', name: 'app_comments_post')]
  public function comments_post(?CommentRequest $commentRQI): Response
  ```

- Add the code to use this `RequestBody` subclass (sorry, it's not really a single line of code if you count in the entity instantiation and the condition checking but the transfer of values from the request into the entity is actually that single line.).
  ```php
  $comment = new Comment();
  if ($commentRQI) {
      $commentRQI->populate($comment);
  }
  ```

Now, any requests coming to that route with POST or PUT bodies, either in JSON format or HTTP form submission, will be converted into the specific parameter and will be able to be utilized by the controller method.

### 3. Custom types for storing & retrieving files and images in database BLOB column

While Doctrine ORM does provide column type for BLOB, to use the such columns to store images and files still require manually reading them from files. Also, any enhancements on images need to be manually done. As for files, should we need to store information such as the file name, size and mime type, those information will need to occupy separate and dedicated columns in the database table alongside the BLOB column.

The bundle comes with custom column types to represent images and files in the database as well as classes which are fine-tuned for handling images and files, as below:

```php
#[ORM\Entity]
class Profile
{
    #[ORM\Column(type: "image", nullable: true)]
    private ?ImageWrapper $photo = null;    
    
    #[ORM\Column(type: "file", nullable: true)]
    private ?FileWrapper $resume = null;    

}
```



#### Column type 'image' & class ImageWrapper

#### Column type 'file' & class FileWrapper   
