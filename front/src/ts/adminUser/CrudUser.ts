import routes from "../routes.ts"

const apiUrl = routes.usersUrl
const token = sessionStorage.getItem('token')
const getUsers = async (page: number = 1) => {
    try {
        return await fetch(`${apiUrl}?page=${page}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

const getUserById = async (id : Number) => {
    try {
        return await fetch(`${apiUrl}/${id}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

const getFilterUsers = async (nickname : String, page: number = 1) => {
    try {
        return await fetch(`${apiUrl}/${nickname}?page=${page}`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        throw error
    }
}

const getUserByToken = async () => {
    try {
        return await fetch(`${apiUrl}Token`, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const addUser = async (data : any) => {
    try {
        return await fetch(apiUrl, {
            method: 'POST',
            body: JSON.stringify(data),
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const updateUser = async (id : number, formData : FormData) => {
    try {
        return await fetch(`${apiUrl}/${id}`, {
            method: 'POST',
            body: formData,
            headers: {

                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

const deleteUser = async (id : number) => {
    try {
        return await fetch(`${apiUrl}/${id}`, {
            method: 'DELETE',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            }
        });
    } catch (error) {
        console.log(error)
        throw error
    }
}

export {
    getUsers,
    getUserById,
    getFilterUsers,
    getUserByToken,
    addUser,
    updateUser,
    deleteUser
}